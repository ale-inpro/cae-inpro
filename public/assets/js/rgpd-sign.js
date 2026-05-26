(function () {
  const canvas = document.getElementById('rgpdSignatureCanvas');
  const form = document.getElementById('rgpdSignForm');
  const hidden = document.getElementById('signature_data');
  const clearBtn = document.getElementById('rgpdClearSig');
  if (!canvas || !form || !hidden) return;

  const ctx = canvas.getContext('2d');
  if (!ctx) return;

  let drawing = false;
  let hasStroke = false;

  const resize = () => {
    const rect = canvas.parentElement.getBoundingClientRect();
    const ratio = window.devicePixelRatio || 1;
    canvas.width = Math.floor(rect.width * ratio);
    canvas.height = Math.floor(180 * ratio);
    canvas.style.width = rect.width + 'px';
    canvas.style.height = '180px';
    ctx.scale(ratio, ratio);
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#111827';
  };

  const pos = (e) => {
    const rect = canvas.getBoundingClientRect();
    const t = e.touches ? e.touches[0] : e;
    return { x: t.clientX - rect.left, y: t.clientY - rect.top };
  };

  const start = (e) => {
    drawing = true;
    const p = pos(e);
    ctx.beginPath();
    ctx.moveTo(p.x, p.y);
    e.preventDefault();
  };

  const move = (e) => {
    if (!drawing) return;
    const p = pos(e);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    hasStroke = true;
    e.preventDefault();
  };

  const end = () => { drawing = false; };

  const clear = () => {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    hasStroke = false;
    hidden.value = '';
  };

  canvas.addEventListener('mousedown', start);
  canvas.addEventListener('mousemove', move);
  window.addEventListener('mouseup', end);
  canvas.addEventListener('touchstart', start, { passive: false });
  canvas.addEventListener('touchmove', move, { passive: false });
  canvas.addEventListener('touchend', end);
  if (clearBtn) clearBtn.addEventListener('click', clear);
  window.addEventListener('resize', () => { clear(); resize(); });

  resize();

  form.addEventListener('submit', (e) => {
    if (!hasStroke) {
      e.preventDefault();
      alert('Dibuje su firma antes de enviar.');
      return;
    }
    hidden.value = canvas.toDataURL('image/png');
  });
})();
