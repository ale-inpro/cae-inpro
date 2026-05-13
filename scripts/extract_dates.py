#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
extract_dates.py  —  Pipeline de extracción de fechas para documentos CAE
Uso: python extract_dates.py <ruta_absoluta> <tipo_documento> [mime_type]
Salida: JSON por stdout
"""

import sys
import io
import json
import re
import os
import base64
import urllib.request
from pathlib import Path
from datetime import date

# Forzar UTF-8 en stdout para que json.dumps funcione correctamente
# cuando PHP llama al script vía exec() en Windows (evita CP1252)
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')


# ─────────────────────────────────────────────────────────────────────────────
# RESULTADO VACÍO POR DEFECTO
# ─────────────────────────────────────────────────────────────────────────────
def manual_result(notes: str, extracted_text: str = '') -> dict:
    return {
        'ok': True,
        'status': 'manual_review',
        'confidence': 0.0,
        'issue_date': None,
        'expires_at': None,
        'notes': notes,
        'extracted_text': extracted_text[:3000],
        'extraction_method': 'none',
    }


# ─────────────────────────────────────────────────────────────────────────────
# PUNTO DE ENTRADA
# ─────────────────────────────────────────────────────────────────────────────
def main():
    if len(sys.argv) < 3:
        print(json.dumps({'ok': False, 'error': 'Uso: extract_dates.py <ruta> <tipo_doc> [mime]'}))
        sys.exit(1)

    file_path = sys.argv[1]
    doc_type  = sys.argv[2]
    mime      = sys.argv[3] if len(sys.argv) > 3 else 'application/pdf'

    if not os.path.isfile(file_path):
        print(json.dumps({'ok': False, 'error': f'Archivo no encontrado: {file_path}'}))
        sys.exit(1)

    # ── PASO 1: Extracción de texto ───────────────────────────────────────
    text   = ''
    method = 'none'

    if 'pdf' in mime.lower():
        text, method = extract_pdfplumber(file_path)
        # Si pdfplumber da poco texto o basura, intentar OCR
        if len(text.strip()) < 80 or is_garbled_text(text):
            ocr_text, ocr_method = extract_ocr_pdf(file_path)
            if len(ocr_text.strip()) > len(text.strip()):
                text, method = ocr_text, ocr_method
    elif mime.lower().startswith('image/'):
        text, method = extract_ocr_image(file_path)

    # ── PASO 2: Regex + dateparser si hay texto legible ───────────────────
    if len(text.strip()) >= 80 and not is_garbled_text(text):
        result = extract_dates_from_text(text, doc_type)
        # Solo devolvemos si encontramos al menos una fecha
        if result.get('status') != 'manual_review':
            result['extracted_text']    = text[:3000]
            result['extraction_method'] = method
            result['ok'] = True
            print(json.dumps(result, ensure_ascii=False, default=str))
            return

    # ── PASO 3: Fallback → GPT-4o Vision (texto insuficiente o sin fechas) ─
    result = extract_dates_vision(file_path, doc_type, mime)
    result['extracted_text']    = text[:3000]
    result['extraction_method'] = 'vision_gpt4o'
    result['ok'] = True
    print(json.dumps(result, ensure_ascii=False, default=str))

# ─────────────────────────────────────────────────────────────────────────────
# HELPERS DE ENTORNO
# ─────────────────────────────────────────────────────────────────────────────
def _find_poppler_path() -> str:
    """Devuelve la ruta de Poppler en Windows buscando ubicaciones comunes."""
    candidates = [
        r'C:\poppler\Library\bin',
        r'C:\poppler\bin',
        r'C:\Program Files\poppler\Library\bin',
        r'C:\Program Files\poppler\bin',
        r'C:\Program Files (x86)\poppler\Library\bin',
    ]
    for c in candidates:
        if os.path.isfile(os.path.join(c, 'pdftoppm.exe')):
            return c
    return ''   # vacío = usar PATH del sistema


# ─────────────────────────────────────────────────────────────────────────────
# EXTRACCIÓN DE TEXTO
# ─────────────────────────────────────────────────────────────────────────────
def extract_pdfplumber(file_path: str) -> tuple[str, str]:
    try:
        import pdfplumber
        parts = []
        with pdfplumber.open(file_path) as pdf:
            for page in pdf.pages:
                t = page.extract_text()
                if t:
                    parts.append(t)
        return '\n'.join(parts), 'pdfplumber'
    except Exception as e:
        return '', f'pdfplumber_error:{e}'

def is_garbled_text(text: str) -> bool:
    """Detecta si el texto extraído es basura (rotado, espejado o ilegible)."""
    if len(text.strip()) < 80:
        return True
    words = text.split()
    if not words:
        return True
    # Ratio de palabras con vocales españolas (texto legible suele tener >30%)
    with_vowels = sum(1 for w in words if re.search(r'[aeiouáéíóúü]', w, re.IGNORECASE))
    vowel_ratio = with_vowels / len(words)
    # Ratio de caracteres especiales raros (texto normal tiene <10%)
    special = len(re.findall(r'[^\w\s/.,;:()\-áéíóúüñÁÉÍÓÚÜÑ]', text))
    special_ratio = special / max(len(text), 1)
    return vowel_ratio < 0.25 or special_ratio > 0.18


def extract_ocr_pdf(file_path: str) -> tuple[str, str]:
    try:
        import pytesseract
        from pdf2image import convert_from_path
        poppler_path = _find_poppler_path() or None
        images = convert_from_path(file_path, dpi=300, poppler_path=poppler_path)
        parts  = []
        for img in images:
            t = pytesseract.image_to_string(img, lang='spa')
            if t:
                parts.append(t)
        return '\n'.join(parts), 'tesseract_pdf'
    except Exception as e:
        return '', f'ocr_pdf_error:{e}'


def extract_ocr_image(file_path: str) -> tuple[str, str]:
    try:
        import pytesseract
        from PIL import Image
        img = Image.open(file_path)
        t   = pytesseract.image_to_string(img, lang='spa')
        return t, 'tesseract_image'
    except Exception as e:
        return '', f'ocr_image_error:{e}'


# ─────────────────────────────────────────────────────────────────────────────
# EXTRACCIÓN DE FECHAS CON REGEX + DATEPARSER
# ─────────────────────────────────────────────────────────────────────────────
def extract_dates_from_text(text: str, doc_type: str) -> dict:
    try:
        import dateparser
    except ImportError:
        return manual_result('dateparser no instalado.', text)

    settings = {
        'PREFER_DAY_OF_MONTH': 'first',
        'DATE_ORDER': 'DMY',          # <— fuerza dd/mm/aaaa, evita "enero vs noviembre"
        'RETURN_AS_TIMEZONE_AWARE': False,
        'PREFER_LOCALE_DATE_ORDER': False,
        'STRICT_PARSING': False,
    }

    # Una sola línea continua para facilitar los regex
    line = re.sub(r'[\r\n\t]+', ' ', text)

    # Normalizar confusión font l↔1 y O↔0 en contextos de fecha
    line = re.sub(r'(?<=\d)l(?=[\d/])', '1', line)   # dígito-l-dígito/slash
    line = re.sub(r'(?<=[/])l(?=\d)', '1', line)      # slash-l-dígito
    line = re.sub(r'\bl(\d)', r'1\1', line)            # l al inicio de número
    line = re.sub(r'(\d)l\b', r'\g<1>1', line)        # número-l al final
    # Casos específicos TGSS: "l2ll l/2024" → "12/11/2024"
    line = re.sub(r'\bl(\d{1,2})ll\s+l/', r'1\g<1>/11/', line)
    line = re.sub(r'(\d{1,2})ll\s+l/(\d{4})', r'\1/11/\2', line)

    # ── Patrones de CADUCIDAD (orden de prioridad decreciente) ────────────
    expiry_patterns = [
        # "válido hasta el 01 de noviembre de 2024"
        r'v[áa]lid[ao]s?\s+hasta\s+el\s+(\d{1,2}\s+de\s+\w+\s+de\s+\d{4})',
        r'v[áa]lid[ao]s?\s+hasta\s+(\d{1,2}[/\-.]\d{1,2}[/\-.]\d{2,4})',
        # "vencimiento: 01/11/2024" o "fecha de vencimiento el 01 de noviembre..."
        r'vencimiento[:\s]+(\d{1,2}[/\-.]\d{1,2}[/\-.]\d{2,4})',
        r'vencimiento\s+el\s+(\d{1,2}\s+de\s+\w+\s+de\s+\d{4})',
        # "fin de cobertura / fin del período"
        r'fin\s+de\s+(?:cobertura|per[íi]odo)[:\s]+(\d{1,2}[/\-.]\d{1,2}[/\-.]\d{2,4})',
        # "desde dd/mm/yyyy al dd/mm/yyyy" — toma la SEGUNDA fecha
        r'desde\s+\d{1,2}[/\-.]\d{1,2}[/\-.]\d{2,4}\s+al\s+(\d{1,2}[/\-.]\d{1,2}[/\-.]\d{2,4})',
        r'desde\s+\d{1,2}\s+de\s+\w+\s+de\s+\d{4}\s+al\s+(\d{1,2}\s+de\s+\w+\s+de\s+\d{4})',
        # "al 01 de junio de 2025" (SPA: fecha final del concierto)
        r'\bal\s+(\d{1,2}\s+de\s+\w+\s+de\s+\d{4})',
        # "hasta el dd/mm/yyyy"
        r'hasta\s+el\s+(\d{1,2}\s+de\s+\w+\s+de\s+\d{4})',
        r'hasta\s+(\d{1,2}[/\-.]\d{1,2}[/\-.]\d{2,4})',
    ]

    # ── Patrones de EXPEDICIÓN / EMISIÓN ────────────────────────────────────
    issue_patterns = [
        r'fecha\s+de\s+expedi(?:ción|cion)[:\s]+(\d{1,2}[/\-.]\d{1,2}[/\-.]\d{2,4})',
        r'expedido\s+(?:el\s+)?(\d{1,2}\s+de\s+\w+\s+de\s+\d{4})',
        r'firmado\s+(?:electr[oó]nicamente\s+)?(?:el\s+)?[^\d]*(\d{1,2}\s+de\s+\w+\s+de\s+\d{4})',
        r'con\s+fecha\s+(\d{1,2}\s+de\s+\w+\s+de\s+\d{4})',
        r'informaci[oó]n\s+obtenidaa?\s*a?\s*(\d{1,2}/\d{1,2}/\d{4})',
        r'obtenida\s*a?\s*(\d{1,2}/\d{1,2}/\d{4})',
        # "Código: XXXX Fecha: 12/11/2024" (TGSS)
        r'[Ff]echa[:\s]+(\d{1,2}/\d{1,2}/\d{4})',
        # AEAT: "con fecha 12 de noviembre de 2024"
        r'con\s+fecha\s+(\d{1,2}\s+de\s+\w+\s+de\s+\d{4})',
    ]

    expires_at = _first_date(expiry_patterns, line, settings)
    issue_date = _first_date(issue_patterns,  line, settings)

    # ── Determinar estado y confianza ────────────────────────────────────
    alert_words = ['caducado', 'anulado', 'cancelado', 'revocado', 'baja', 'vencido']
    for aw in alert_words:
        if aw in text.lower():
            return {
                'status': 'in_review',
                'confidence': 0.60,
                'issue_date': issue_date,
                'expires_at': expires_at,
                'notes': f'Alerta: palabra "{aw}" encontrada en el documento.',
            }

    if issue_date is None and expires_at is None:
        return manual_result('No se encontraron fechas con los patrones conocidos.', text)

    confidence = 0.90 if (issue_date and expires_at) else 0.72
    notes = (
        'Fechas extraídas correctamente.'
        if (issue_date and expires_at)
        else (
            'Solo fecha de caducidad encontrada.'
            if expires_at
            else 'Solo fecha de emisión encontrada; caducidad se calculará por regla.'
        )
    )

    return {
        'status':     'approved',
        'confidence': confidence,
        'issue_date': issue_date,
        'expires_at': expires_at,
        'notes':      notes,
    }


def _first_date(patterns: list, text: str, settings: dict) -> 'str | None':
    """Devuelve la primera fecha parseada que encuentre en la lista de patrones."""
    try:
        import dateparser
    except ImportError:
        return None

    for pattern in patterns:
        m = re.search(pattern, text, re.IGNORECASE)
        if m:
            parsed = dateparser.parse(m.group(1), settings=settings, languages=['es'])
            if parsed:
                return parsed.date().isoformat()
    return None


# ─────────────────────────────────────────────────────────────────────────────
# FALLBACK: GPT-4o VISIÓN
# ─────────────────────────────────────────────────────────────────────────────
def extract_dates_vision(file_path: str, doc_type: str, mime: str) -> dict:
    api_key = _read_api_key()
    if not api_key:
        return manual_result('Vision fallback: API key no disponible.')

    image_b64, image_mime = _to_base64_image(file_path, mime)
    if not image_b64:
        return manual_result('No se pudo convertir el documento a imagen para Vision.')

    prompt = f"""Analiza este documento de tipo: {doc_type}

Extrae EXACTAMENTE los siguientes campos:
- issue_date: fecha de expedición/emisión → formato YYYY-MM-DD (null si no aparece)
- expires_at: fecha de caducidad/vencimiento/validez hasta → YYYY-MM-DD (null si no aparece)
- status: "approved" si parece válido | "in_review" si hay dudas | "rejected" si está caducado/anulado
- confidence: número 0.0–1.0
- notes: una línea breve explicando qué encontraste

REGLAS CRÍTICAS:
1. Las fechas están en formato español DD/MM/AAAA. NO confundas mes 11 (noviembre) con 01 (enero).
2. Si el documento dice "válido desde X hasta Y" o "desde X al Y", issue_date = X y expires_at = Y.
3. Si pone "validez de seis meses desde la fecha de expedición", calcula la fecha concreta.
4. Si el texto dice "al corriente" o "POSITIVO" es un documento válido.
5. Para documentos de SEGURO o PÓLIZA: issue_date = fecha de INICIO DE COBERTURA (no la fecha de la factura ni del recibo de pago). expires_at = fecha de FIN DE COBERTURA. Si issue_date y expires_at son incompatibles (expires_at anterior a issue_date), seguramente confundiste la fecha de factura con el inicio de cobertura; corrígelo.
6. Si aparecen múltiples fechas, prioriza las que corresponden al período de vigencia del documento, no fechas administrativas secundarias.
7. Devuelve SOLO JSON válido, sin explicaciones extra."""

    payload = {
        'model': 'gpt-4o',
        'temperature': 0,
        'max_tokens': 400,
        'messages': [{
            'role': 'user',
            'content': [
                {'type': 'text', 'text': prompt},
                {
                    'type': 'image_url',
                    'image_url': {
                        'url': f'data:{image_mime};base64,{image_b64}',
                        'detail': 'high',
                    },
                },
            ],
        }],
        'response_format': {
            'type': 'json_schema',
            'json_schema': {
                'name': 'doc_dates',
                'strict': True,
                'schema': {
                    'type': 'object',
                    'properties': {
                        'issue_date':  {'type': ['string', 'null']},
                        'expires_at':  {'type': ['string', 'null']},
                        'status':      {'type': 'string'},
                        'confidence':  {'type': 'number'},
                        'notes':       {'type': 'string'},
                    },
                    'required': ['issue_date', 'expires_at', 'status', 'confidence', 'notes'],
                    'additionalProperties': False,
                },
            },
        },
    }

    try:
        data = json.dumps(payload).encode('utf-8')
        req  = urllib.request.Request(
            'https://api.openai.com/v1/chat/completions',
            data=data,
            headers={
                'Content-Type': 'application/json',
                'Authorization': f'Bearer {api_key}',
            },
        )
        with urllib.request.urlopen(req, timeout=90) as resp:
            raw = json.loads(resp.read().decode('utf-8'))

        content = raw['choices'][0]['message']['content']
        m = re.search(r'\{.*\}', content, re.DOTALL)
        if m:
            content = m.group(0)
        parsed = json.loads(content)

        # Validar formato YYYY-MM-DD
        for key in ['issue_date', 'expires_at']:
            v = parsed.get(key)
            if v and not re.match(r'^\d{4}-\d{2}-\d{2}$', str(v)):
                parsed[key] = None

        status = parsed.get('status', 'manual_review')
        if status not in ['approved', 'in_review', 'rejected', 'manual_review']:
            status = 'manual_review'

        return {
            'status':     status,
            'confidence': float(parsed.get('confidence', 0.5)),
            'issue_date': parsed.get('issue_date'),
            'expires_at': parsed.get('expires_at'),
            'notes':      f"[Vision gpt-4o] {parsed.get('notes', '')}",
        }

    except Exception as e:
        return manual_result(f'Vision API error: {e}')


# ─────────────────────────────────────────────────────────────────────────────
# HELPERS
# ─────────────────────────────────────────────────────────────────────────────
def _read_api_key() -> str:
    """Lee openai_api_key desde config/ai.php del proyecto."""
    config_path = os.path.join(os.path.dirname(__file__), '..', 'config', 'ai.php')
    try:
        with open(config_path, 'r', encoding='utf-8') as f:
            content = f.read()
        m = re.search(r"'openai_api_key'\s*=>\s*'([^']+)'", content)
        return m.group(1) if m else ''
    except Exception:
        return ''


def _to_base64_image(file_path: str, mime: str) -> tuple[str, str]:
    """Convierte un archivo (PDF o imagen) a base64 JPEG para Vision."""
    if 'pdf' in mime.lower():
        try:
            from pdf2image import convert_from_path
            import io
            poppler_path = _find_poppler_path() or None
            images = convert_from_path(
                file_path, dpi=200, first_page=1, last_page=1,
                poppler_path=poppler_path,
            )
            if not images:
                return '', ''
            buf = io.BytesIO()
            images[0].save(buf, format='JPEG', quality=85)
            return base64.b64encode(buf.getvalue()).decode('utf-8'), 'image/jpeg'
        except Exception:
            return '', ''
    else:
        try:
            with open(file_path, 'rb') as f:
                data = f.read()
            ext = Path(file_path).suffix.lower().lstrip('.')
            img_mime = {'jpg': 'image/jpeg', 'jpeg': 'image/jpeg', 'png': 'image/png'}.get(ext, 'image/jpeg')
            return base64.b64encode(data).decode('utf-8'), img_mime
        except Exception:
            return '', ''


if __name__ == '__main__':
    main()