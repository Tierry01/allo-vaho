/* ==========================================================================
   Page : Connexion administrateur + vérification en deux étapes
   → resources/views/auth/login.blade.php · resources/js/admin/pages/login.js
   Toutes les réponses serveur sont SIMULÉES (scénarios du panneau Démo).
   ========================================================================== */
(function () {
  'use strict';
  const A = window.AVAuth, $ = A.$, $$ = A.$$;
  A.init();
  const card = $('[data-auth-card]');
  const demo = A.demo();

  /* ---------- Étape 1 : identifiants ---------- */
  const form = $('#login-form'), email = $('#login-email'), pwd = $('#login-password'), btn = $('#login-submit'), msg = $('#login-message');
  const prefill = new URLSearchParams(location.search).get('email');
  if (prefill) A.setValue(email, prefill);
  if (matchMedia('(min-width: 768px)').matches) (prefill ? pwd : email).focus({ preventScroll: true });

  const lock = on => { [email, pwd].forEach(i => A.field.setDisabled(i, on)); $('#forgot-link').disabled = on; };

  form.addEventListener('submit', async e => {
    e.preventDefault();
    A.message(msg, null);
    const v = email.value.trim(); let first = null;
    if (!v) { A.field.setError(email, 'Saisissez votre adresse e-mail.'); first = email; }
    else if (!A.EMAIL_RE.test(v)) { A.field.setError(email, 'Cette adresse e-mail n’est pas valide.'); first = email; }
    else A.field.clear(email);
    if (!pwd.value) { A.field.setError(pwd, 'Saisissez votre mot de passe.'); first = first || pwd; } else A.field.clear(pwd);
    if (first) { first.focus(); return; }

    A.setLoading(btn, true, 'Connexion…'); lock(true);
    await A.wait(1100);
    A.setLoading(btn, false); lock(false);

    const sc = demo.get('login');
    if (sc === 'invalid') {
      A.message(msg, { type: 'error', title: 'Les informations saisies sont incorrectes.', text: 'Vérifiez votre adresse e-mail et votre mot de passe, puis réessayez.', code: 'AUTH-001' });
      A.setValue(pwd, ''); A.hidePassword(pwd);
      A.field.control(pwd).classList.add('is-invalid'); pwd.setAttribute('aria-invalid', 'true');
      pwd.focus(); return;
    }
    if (sc === 'server') {
      A.message(msg, { type: 'error', title: 'Une erreur est survenue. Réessayez.', text: 'Le service de connexion ne répond pas pour le moment.', code: 'AUTH-500' });
      btn.focus(); return;
    }
    goOtp();
  });

  /* ---------- Mot de passe oublié (interface uniquement) ---------- */
  const fForm = $('#forgot-form'), fEmail = $('#forgot-email'), fBtn = $('#forgot-submit');
  $('#forgot-link').addEventListener('click', () => {
    A.setValue(fEmail, email.value.trim());
    fForm.hidden = false; $('#forgot-done').hidden = true; A.field.clear(fEmail);
    A.showView(card, 'forgot');
  });
  fForm.addEventListener('submit', async e => {
    e.preventDefault();
    const v = fEmail.value.trim();
    if (!v) { A.field.setError(fEmail, 'Saisissez votre adresse e-mail.'); fEmail.focus(); return; }
    if (!A.EMAIL_RE.test(v)) { A.field.setError(fEmail, 'Cette adresse e-mail n’est pas valide.'); fEmail.focus(); return; }
    A.setLoading(fBtn, true, 'Envoi…'); A.field.setDisabled(fEmail, true);
    await A.wait(900);
    A.setLoading(fBtn, false); A.field.setDisabled(fEmail, false);
    fForm.hidden = true; $('#forgot-done').hidden = false;
    $('#forgot-done-back').focus();
  });
  document.addEventListener('click', e => {
    if (e.target.closest('[data-back-login]')) { stopCd(); A.showView(card, 'credentials'); (email.value ? pwd : email).focus(); }
  });

  /* ---------- Étape 2 : vérification en deux étapes ---------- */
  const otpForm = $('#otp-form'), otpMsg = $('#otp-message'), otpBtn = $('#otp-submit');
  const resendBtn = $('#otp-resend'), timer = $('#otp-timer'), countdown = $('#otp-countdown');
  let busy = false, attempts = 5, cancel = null;
  const stopCd = () => { if (cancel) cancel(); cancel = null; };
  const startCd = () => {
    stopCd(); resendBtn.hidden = true; timer.hidden = false;
    cancel = A.countdown(42, s => countdown.textContent = A.mmss(s), () => { timer.hidden = true; resendBtn.hidden = false; });
  };
  const allowResendNow = () => { stopCd(); timer.hidden = true; resendBtn.hidden = false; };
  const mask = v => { const [l, d] = v.split('@'); return l.slice(0, 2) + '•'.repeat(Math.max(3, Math.min(6, l.length - 2))) + '@' + d; };

  const otp = A.otp($('#otp'), { onComplete: () => verify() });

  function goOtp() {
    attempts = 5;
    $('#otp-account').textContent = mask(email.value.trim());
    A.message(otpMsg, null);
    A.showView(card, 'otp', { focus: false });
    otp.setState(''); otp.clear();
    startCd();
  }
  async function verify() {
    if (busy) return;
    if (otp.value().length < 6) {
      otp.setState('error');
      A.message(otpMsg, { type: 'error', title: 'Entrez les 6 chiffres du code.' });
      otp.focus(); return;
    }
    busy = true; A.message(otpMsg, null);
    otp.setState('loading'); A.setLoading(otpBtn, true, 'Vérification…');
    await A.wait(900);
    A.setLoading(otpBtn, false); busy = false;

    const sc = demo.get('otp');
    if (sc === 'wrong') {
      attempts = Math.max(0, attempts - 1);
      otp.setState(''); otp.clear(); otp.setState('error');
      A.message(otpMsg, { type: 'error', title: 'Ce code est incorrect.', text: `Vérifiez-le et réessayez. ${attempts} tentative${attempts > 1 ? 's' : ''} restante${attempts > 1 ? 's' : ''}.`, code: 'AUTH-2FA-01' });
      return;
    }
    if (sc === 'expired') {
      otp.setState(''); otp.clear(); otp.setState('error');
      allowResendNow();
      A.message(otpMsg, { type: 'warning', title: 'Ce code a expiré.', text: 'Demandez un nouveau code pour continuer.', code: 'AUTH-2FA-02', action: '<button type="button" class="av-auth-message__action" data-resend-inline>Renvoyer le code</button>' });
      return;
    }
    otp.setState('success'); stopCd();
    await A.wait(420);
    A.showView(card, 'success');
    setTimeout(() => { location.href = 'index.html'; }, 1900);
  }
  otpForm.addEventListener('submit', e => { e.preventDefault(); verify(); });

  async function resend() {
    resendBtn.disabled = true; resendBtn.textContent = 'Envoi…';
    $$('[data-resend-inline]').forEach(b => b.disabled = true);
    await A.wait(700);
    resendBtn.disabled = false; resendBtn.textContent = 'Renvoyer le code';
    if (demo.get('otp') === 'expired') demo.set('otp', 'valid');
    otp.setState(''); otp.clear();
    A.message(otpMsg, { type: 'success', title: 'Un nouveau code a été envoyé.', text: 'Saisissez-le ci-dessous. Le code précédent n’est plus valable.' });
    startCd();
  }
  resendBtn.addEventListener('click', resend);
  otpMsg.addEventListener('click', e => { if (e.target.closest('[data-resend-inline]')) resend(); });
  $('#otp-method').addEventListener('click', () => {
    A.message(otpMsg, { type: 'info', title: 'Autres modes de vérification', text: 'Les modes proposés dépendront de la configuration de votre compte. Si vous n’avez plus accès à votre moyen de vérification, contactez un Super Admin.' });
  });
})();
