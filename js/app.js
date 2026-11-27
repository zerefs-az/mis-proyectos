document.addEventListener('DOMContentLoaded', () => {
  // Mostrar / ocultar contraseña en cualquier formulario
  document.querySelectorAll('[data-toggle-pass]').forEach(btn => {
    btn.addEventListener('click', () => {
      const inp = document.getElementById(btn.dataset.togglePass);
      if (!inp) return;
      const show = inp.type === 'password';
      inp.type = show ? 'text' : 'password';
      btn.textContent = show ? 'Ocultar' : 'Ver';
    });
  });

  // Vista previa en vivo al seleccionar portada de libro
  document.querySelectorAll('[data-cover-input]').forEach(input => {
    input.addEventListener('change', () => {
      const img = document.getElementById(input.dataset.coverInput);
      const file = input.files && input.files[0];
      if (!img || !file) return;
      const reader = new FileReader();
      reader.onload = e => { img.src = e.target.result; };
      reader.readAsDataURL(file);
    });
  });

  // Vista previa proporcional (1:1) al seleccionar foto de perfil
  document.querySelectorAll('[data-avatar-input]').forEach(input => {
    input.addEventListener('change', () => {
      const img = document.getElementById(input.dataset.avatarInput);
      const initial = document.getElementById(input.dataset.avatarInitial);
      const quitarFlag = document.getElementById('quitar-foto-flag');
      const file = input.files && input.files[0];
      if (!img || !file) return;
      const reader = new FileReader();
      reader.onload = e => {
        img.src = e.target.result;
        img.style.display = 'block';
        if (initial) initial.style.display = 'none';
        if (quitarFlag) quitarFlag.value = '';
      };
      reader.readAsDataURL(file);
    });
  });

  // Quitar foto de perfil (vuelve a mostrar la inicial)
  const btnQuitar = document.getElementById('btn-quitar-foto');
  if (btnQuitar) {
    btnQuitar.addEventListener('click', () => {
      const img = document.getElementById(btnQuitar.dataset.clearImg || 'perfil-avatar-img');
      const initial = document.getElementById(btnQuitar.dataset.clearInit || 'perfil-avatar-initial');
      const inp = document.getElementById(btnQuitar.dataset.clearInput || 'perfil-foto-input');
      const quitarFlag = document.getElementById('quitar-foto-flag');
      if (img) { img.src = ''; img.style.display = 'none'; }
      if (initial) initial.style.display = 'inline-flex';
      if (inp) inp.value = '';
      if (quitarFlag) quitarFlag.value = '1';
    });
  }

  // Confirmacion segura en botones de eliminacion
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (!window.confirm(el.dataset.confirm)) {
        e.preventDefault();
      }
    });
  });
});
