#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
extract_aeat_csv.py — Extrae CSV AEAT (16 caracteres) de certificados Hacienda.
Uso: python extract_aeat_csv.py <ruta_absoluta_pdf> [mime_type]
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

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

CSV_STRICT = re.compile(r'\b([A-Z0-9]{16})\b')
CSV_SPACED = re.compile(r'(?:[A-Z0-9]{4}\s+){3}[A-Z0-9]{4}', re.IGNORECASE)
CSV_LABEL = re.compile(
    r'(?:CSV|C[ÓO]DIGO\s+SEGURO(?:\s+DE\s+VERIFICACI[ÓO]N)?|'
    r'C[ÓO]DIGO\s+DE\s+VERIFICACI[ÓO]N|VERIFICACI[ÓO]N)[:\s\-]*'
    r'([A-Z0-9\s]{16,24})',
    re.IGNORECASE,
)
LABEL_WINDOW = re.compile(
    r'(?:CSV|C[ÓO]DIGO\s+SEGURO|C[ÓO]DIGO\s+DE\s+VERIFICACI[ÓO]N|VERIFICACI[ÓO]N)',
    re.IGNORECASE,
)


def normalize_csv(raw: str) -> str | None:
    s = re.sub(r'\s+', '', (raw or '').upper())
    if len(s) == 16 and s.isalnum():
        return s
    return None


def manual_result(notes: str, candidates: list[str] | None = None,
                  method: str = 'none', suggested: str | None = None) -> dict:
    cands = list(dict.fromkeys(candidates or []))
    return {
        'ok': True,
        'csv': None,
        'candidates': cands,
        'suggested_csv': suggested,
        'extraction_method': method,
        'notes': notes,
        'error': None,
        'extraction_reliable': False,
    }


def success_result(csv: str, candidates: list[str], method: str,
                   extraction_reliable: bool | None = None) -> dict:
    cands = list(dict.fromkeys(candidates or [csv]))
    if extraction_reliable is None:
        extraction_reliable = method in ('pdfplumber', 'pdf_native')
    labels = {
        'pdfplumber': 'PDF digital',
        'tesseract_pdf': 'OCR',
        'tesseract_image': 'OCR imagen',
        'vision_gpt4o': 'visión IA',
    }
    label = labels.get(method, method)
    return {
        'ok': True,
        'csv': csv,
        'candidates': cands,
        'suggested_csv': csv,
        'extraction_method': method,
        'notes': f'CSV detectado automáticamente ({label}): {csv}',
        'error': None,
        'extraction_reliable': extraction_reliable,
    }


def find_csv_candidates(text: str) -> tuple[list[str], dict[str, int]]:
    upper = (text or '').upper()
    compact = re.sub(r'\s+', ' ', upper)
    found: dict[str, int] = {}

    def add(raw: str, score: int) -> None:
        csv = normalize_csv(raw)
        if csv:
            found[csv] = max(found.get(csv, 0), score)

    for m in CSV_STRICT.finditer(compact):
        add(m.group(1), 1)

    for m in CSV_SPACED.finditer(compact):
        add(m.group(0), 2)

    for m in CSV_LABEL.finditer(compact):
        add(m.group(1), 12)

    for label in LABEL_WINDOW.finditer(compact):
        start = label.end()
        window = compact[start:start + 120]
        for m in CSV_STRICT.finditer(window):
            add(m.group(1), 10)
        for m in CSV_SPACED.finditer(window):
            add(m.group(0), 10)
        chunk = re.sub(r'[^A-Z0-9]', '', window)
        for i in range(max(0, len(chunk) - 15)):
            add(chunk[i:i + 16], 8)

    return list(found.keys()), found


def pick_csv(candidates: list[str], scores: dict[str, int]) -> tuple[str | None, str | None]:
    if not candidates:
        return None, None
    if len(candidates) == 1:
        return candidates[0], candidates[0]

    ranked = sorted(candidates, key=lambda c: scores.get(c, 0), reverse=True)
    best = ranked[0]
    second = ranked[1] if len(ranked) > 1 else None
    best_score = scores.get(best, 0)
    second_score = scores.get(second, 0) if second else 0

    if best_score >= 10 and best_score > second_score:
        return best, best

    return None, best


def _find_poppler_path() -> str:
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
    return ''


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
    if len(text.strip()) < 40:
        return True
    words = text.split()
    if not words:
        return True
    with_vowels = sum(1 for w in words if re.search(r'[aeiouáéíóúü]', w, re.IGNORECASE))
    vowel_ratio = with_vowels / len(words)
    special = len(re.findall(r'[^\w\s/.,;:()\-áéíóúüñÁÉÍÓÚÜÑ]', text))
    special_ratio = special / max(len(text), 1)
    return vowel_ratio < 0.25 or special_ratio > 0.18


def extract_ocr_pdf(file_path: str) -> tuple[str, str]:
    try:
        import pytesseract
        from pdf2image import convert_from_path
        poppler_path = _find_poppler_path() or None
        images = convert_from_path(file_path, dpi=300, poppler_path=poppler_path)
        config = r'--psm 6 -c tessedit_char_whitelist=ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
        parts = []
        for img in images:
            w, h = img.size
            footer = img.crop((0, int(h * 0.55), w, h))
            t1 = pytesseract.image_to_string(img, lang='spa', config=config)
            t2 = pytesseract.image_to_string(footer, lang='spa', config=config)
            if t1:
                parts.append(t1)
            if t2:
                parts.append(t2)
        return '\n'.join(parts), 'tesseract_pdf'
    except Exception as e:
        return '', f'ocr_pdf_error:{e}'


def extract_text_pipeline(file_path: str, mime: str) -> tuple[str, str]:
    text, method = '', 'none'

    if 'pdf' in mime.lower():
        text, method = extract_pdfplumber(file_path)
        if len(text.strip()) < 40 or is_garbled_text(text):
            ocr_text, ocr_method = extract_ocr_pdf(file_path)
            if len(ocr_text.strip()) > len(text.strip()):
                text, method = ocr_text, ocr_method
    elif mime.lower().startswith('image/'):
        try:
            import pytesseract
            from PIL import Image
            text = pytesseract.image_to_string(Image.open(file_path), lang='spa')
            method = 'tesseract_image'
        except Exception:
            text, method = '', 'none'

    return text, method


def extract_csv_from_text(text: str, method: str) -> dict:
    candidates, scores = find_csv_candidates(text)
    csv, suggested = pick_csv(candidates, scores)

    if csv:
        reliable = len(candidates) == 1 and scores.get(csv, 0) >= 10
        return success_result(csv, candidates, method, extraction_reliable=reliable)

    if suggested:
        return manual_result(
            f'Varios candidatos CSV ({", ".join(candidates)}). Sugerido: {suggested}. Confirma manualmente.',
            candidates,
            method,
            suggested,
        )

    if candidates:
        return manual_result(
            f'Varios candidatos CSV ({", ".join(candidates)}). Indica el correcto manualmente.',
            candidates,
            method,
        )

    return manual_result('No se encontró ningún CSV en el PDF.', [], method)


def _read_api_key() -> str:
    config_path = os.path.join(os.path.dirname(__file__), '..', 'config', 'ai.php')
    try:
        with open(config_path, 'r', encoding='utf-8') as f:
            content = f.read()
        m = re.search(r"'openai_api_key'\s*=>\s*'([^']+)'", content)
        return m.group(1) if m else ''
    except Exception:
        return ''


def _pdf_page_images(file_path: str, dpi: int = 300) -> list:
    """Devuelve PIL Images de todas las páginas del PDF."""
    try:
        from pdf2image import convert_from_path
        poppler_path = _find_poppler_path() or None
        return convert_from_path(file_path, dpi=dpi, poppler_path=poppler_path) or []
    except Exception:
        return []


def _pil_to_b64_jpeg(img, quality: int = 92) -> str:
    import io as _io
    buf = _io.BytesIO()
    img.save(buf, format='JPEG', quality=quality)
    return base64.b64encode(buf.getvalue()).decode('utf-8')


def _vision_images_for_pdf(file_path: str) -> list[tuple[str, str]]:
    """
    Imágenes para Vision: última página completa + recorte del pie (CSV).
    Devuelve lista de (base64_jpeg, descripcion).
    """
    images = _pdf_page_images(file_path, dpi=300)
    if not images:
        return []

    out: list[tuple[str, str]] = []
    last = images[-1]
    w, h = last.size

    # Página completa (última)
    out.append((_pil_to_b64_jpeg(last), 'Ultima pagina completa del certificado AEAT'))

    # Pie inferior ~42% (donde suele estar el CSV)
    top = int(h * 0.58)
    footer = last.crop((0, top, w, h))
    out.append((_pil_to_b64_jpeg(footer, quality=95), 'Recorte ampliado del pie: bloque Codigo Seguro Verificacion / CSV'))

    # Si hay 2+ paginas, también el pie de la penultima por si el CSV está repetido
    if len(images) >= 2:
        prev = images[-2]
        pw, ph = prev.size
        prev_footer = prev.crop((0, int(ph * 0.55), pw, ph))
        out.append((_pil_to_b64_jpeg(prev_footer, quality=95), 'Recorte del pie de la pagina anterior'))

    return out


def _vision_images_for_file(file_path: str, mime: str) -> list[tuple[str, str]]:
    if 'pdf' in mime.lower():
        return _vision_images_for_pdf(file_path)

    try:
        with open(file_path, 'rb') as f:
            data = f.read()
        ext = Path(file_path).suffix.lower().lstrip('.')
        img_mime = {'jpg': 'image/jpeg', 'jpeg': 'image/jpeg', 'png': 'image/png'}.get(ext, 'image/jpeg')
        b64 = base64.b64encode(data).decode('utf-8')
        return [(b64, 'Imagen del certificado')]
    except Exception:
        return []

def _call_vision_csv_api(api_key: str, image_parts: list[dict], prompt: str) -> dict | None:
    payload = {
        'model': 'gpt-4o',
        'temperature': 0,
        'max_tokens': 350,
        'messages': [{
            'role': 'user',
            'content': [{'type': 'text', 'text': prompt}] + image_parts,
        }],
        'response_format': {
            'type': 'json_schema',
            'json_schema': {
                'name': 'aeat_csv_strict',
                'strict': True,
                'schema': {
                    'type': 'object',
                    'properties': {
                        'csv': {'type': ['string', 'null']},
                        'csv_characters': {
                            'type': ['array', 'null'],
                            'items': {'type': 'string'},
                        },
                        'is_aeat_hacienda_certificate': {'type': 'boolean'},
                        'csv_label_visible': {'type': 'boolean'},
                        'occurrences_match': {'type': 'boolean'},
                        'confidence': {'type': 'number'},
                        'notes': {'type': 'string'},
                    },
                    'required': [
                        'csv', 'csv_characters',
                        'is_aeat_hacienda_certificate', 'csv_label_visible',
                        'occurrences_match', 'confidence', 'notes',
                    ],
                    'additionalProperties': False,
                },
            },
        },
    }

    data = json.dumps(payload).encode('utf-8')
    req = urllib.request.Request(
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
    return json.loads(content)


def _csv_from_vision_parsed(parsed: dict) -> tuple[str | None, float]:
    """Prioriza csv_characters (16 posiciones) sobre csv agregado."""
    chars = parsed.get('csv_characters')
    if isinstance(chars, list) and len(chars) == 16:
        joined = normalize_csv(''.join(str(c) for c in chars))
        if joined:
            return joined, float(parsed.get('confidence', 0.0))

    return normalize_csv(str(parsed.get('csv') or '')), float(parsed.get('confidence', 0.0))


def _vision_csv_accepted(parsed: dict) -> tuple[str | None, float]:
    """Solo acepta CSV si el documento parece certificado AEAT con etiqueta CSV visible."""
    if not isinstance(parsed, dict):
        return None, 0.0

    if not bool(parsed.get('is_aeat_hacienda_certificate', False)):
        return None, float(parsed.get('confidence', 0.0))
    if not bool(parsed.get('csv_label_visible', False)):
        return None, float(parsed.get('confidence', 0.0))
    if not bool(parsed.get('occurrences_match', False)):
        return None, float(parsed.get('confidence', 0.0))

    csv, confidence = _csv_from_vision_parsed(parsed)
    if csv is None or len(csv) != 16:
        return None, confidence

    return csv, confidence


def extract_csv_vision(file_path: str, mime: str) -> dict:
    api_key = _read_api_key()
    if not api_key:
        return manual_result('El análisis automático no está disponible en este momento.')

    shots = _vision_images_for_file(file_path, mime)
    if not shots:
        return manual_result('El formato del archivo no permite extraer el CSV automáticamente.')

    image_parts = []
    for b64, desc in shots:
        image_parts.append({'type': 'text', 'text': desc})
        image_parts.append({
            'type': 'image_url',
            'image_url': {
                'url': f'data:image/jpeg;base64,{b64}',
                'detail': 'high',
            },
        })

    prompt = """Eres un extractor OCR especializado en certificados de la AEAT (Agencia Tributaria).

        OBJETIVO: leer el CSV / Codigo Seguro de Verificacion (exactamente 16 caracteres A-Z y 0-9).

        DONDE BUSCAR:
        - Pie del documento, junto a "Codigo Seguro Verificacion", "CSV" o "Autenticidad verificable mediante Codigo Seguro Verificacion".
        - El CSV suele repetirse 2 veces en el pie; ambas copias DEBEN ser identicas.

        METODO OBLIGATORIO:
        1. Localiza TODAS las apariciones del codigo de 16 caracteres.
        2. Lee caracter a caracter (posicion 1..16) en la copia mas legible.
        3. Rellena csv_characters con exactamente 16 strings de 1 caracter (MAYUSCULAS).
        4. csv = concatenacion de csv_characters sin espacios.
        5. occurrences_match = true solo si todas las copias visibles coinciden.
        6. is_aeat_hacienda_certificate=true SOLO si es un certificado AEAT de estar al corriente con Hacienda.
        7. csv_label_visible=true SOLO si ves la etiqueta "Codigo Seguro Verificacion" o "CSV".
        8. Si NO es certificado AEAT o NO ves la etiqueta CSV, devuelve csv=null, csv_characters=null,
        is_aeat_hacienda_certificate=false, csv_label_visible=false.
        9. NO inventes un codigo de 16 caracteres en documentos que no sean de Hacienda.

        CONFUSIONES CRITICAS (no las intercambies):
        - 3 vs I: el 3 tiene dos curvas apiladas; la I es una barra vertical sin curvas.
        - G vs L: la G tiene trazo horizontal cerrado abajo; la L es vertical con trazo horizontal SOLO abajo.
        - O vs 0: O es letra, 0 es numero.
        - 1 vs I vs L, S vs 5, B vs 8, Z vs 2.

        El CSV mezcla letras y numeros; en cada posicion decide si es digito (0-9) o letra (A-Z).
        Si las dos copias del pie difieren en un caracter, corrige usando la copia mas nitida.
        No inventes caracteres. Si no puedes leer los 16 con seguridad, csv=null y csv_characters=null."""

    try:
        parsed = _call_vision_csv_api(api_key, image_parts, prompt)
        if not isinstance(parsed, dict):
            return manual_result('Respuesta Vision no valida.')

        csv, confidence = _vision_csv_accepted(parsed)

        if csv and confidence >= 0.85:
            return success_result(csv, [csv], 'vision_gpt4o', extraction_reliable=True)

        footer_shots = [s for s in shots if 'Recorte' in s[1]]
        if footer_shots and csv:
            footer_parts = []
            for b64, desc in footer_shots:
                footer_parts.append({'type': 'text', 'text': desc})
                footer_parts.append({
                    'type': 'image_url',
                    'image_url': {'url': f'data:image/jpeg;base64,{b64}', 'detail': 'high'},
                })

            prompt2 = f"""Relee SOLO el codigo de 16 caracteres del pie del certificado AEAT.

Primera lectura (puede tener errores OCR): {csv}

Lee caracter a caracter (posiciones 1..16). Corrige 3 vs I, GL vs LG, 0 vs O.
Devuelve csv_characters (16 elementos) y csv.
is_aeat_hacienda_certificate y csv_label_visible deben ser true.
occurrences_match=true solo si las copias del pie coinciden."""

            parsed2 = _call_vision_csv_api(api_key, footer_parts, prompt2)
            if isinstance(parsed2, dict):
                csv2, conf2 = _vision_csv_accepted(parsed2)
                if csv2 and conf2 >= 0.80:
                    return success_result(csv2, [csv, csv2], 'vision_gpt4o', extraction_reliable=True)

        if not bool(parsed.get('is_aeat_hacienda_certificate', False)) or not bool(parsed.get('csv_label_visible', False)):
            return manual_result(
                'No se ha detectado un certificado AEAT con codigo CSV visible. Revision manual.',
                [], 'vision_gpt4o',
            )

        if csv:
            return manual_result(
                f'CSV detectado pero no confirmado visualmente ({confidence:.2f}): {csv}. Confirma manualmente.',
                [csv], 'vision_gpt4o', csv,
            )

        return manual_result(
            str(parsed.get('notes') or 'No se detecto CSV en el documento.'),
            [], 'vision_gpt4o',
        )
    except Exception as e:
        return manual_result(f'Error Vision: {e}', [], 'vision_gpt4o')


def main() -> None:
    if len(sys.argv) < 2:
        print(json.dumps({'ok': False, 'error': 'Uso: extract_aeat_csv.py <ruta_pdf> [mime]'}))
        sys.exit(1)

    file_path = sys.argv[1]
    mime = sys.argv[2] if len(sys.argv) > 2 else 'application/pdf'

    if not os.path.isfile(file_path):
        print(json.dumps({'ok': False, 'error': f'Archivo no encontrado: {file_path}'}))
        sys.exit(1)

    text, method = extract_text_pipeline(file_path, mime)
    if len(text.strip()) >= 20 and not is_garbled_text(text):
        result = extract_csv_from_text(text, method)
        if result.get('csv'):
            print(json.dumps(result, ensure_ascii=False))
            return

    vision_result = extract_csv_vision(file_path, mime)
    if vision_result.get('csv'):
        print(json.dumps(vision_result, ensure_ascii=False))
        return

    if len(text.strip()) >= 20 and not is_garbled_text(text):
        print(json.dumps(extract_csv_from_text(text, method), ensure_ascii=False))
        return

    print(json.dumps(vision_result, ensure_ascii=False))


if __name__ == '__main__':
    main()