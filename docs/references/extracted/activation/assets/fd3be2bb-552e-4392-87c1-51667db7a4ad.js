/* ==========================================================================
   Page : Activation d'un compte administrateur invité
   → resources/views/auth/activate-account.blade.php · resources/js/admin/pages/activate.js
   Invitation et réponses serveur SIMULÉES (données fictives).
   ========================================================================== */
(function () {
  'use strict';
  const A = window.AVAuth, $ = A.$, $$ = A.$$;
  A.init();
  const card = $('[data-auth-card]');

  // Données fictives de l'invitation (en production : fournies par le contrôleur à partir du jeton)
  const INVITE = { name: 'Jean Dupont', email: 'jean.dupont@allovaho.com', role: 'Support', invitedBy: 'Estelle Fouda', inviterRole: 'Super Admin', validUntil: '1 oct. 2026 · 14:00', expiredOn: '27 sept. 2026' };
  $$('[data-invite]').forEach(el => el.textContent = INVITE[el.dataset.invite]);

  const panel = {
    title: $('[data-panel-title]'), text: $('[data-panel-text]'), card: $('[data-invite-card]'),
    set(valid) {
      this.title.innerHTML = valid ? 'Votre accès<br>est prêt.' : 'Accès sur<br>invitation.';
      this.text.textContent = valid ? 'Choisissez votre mot de passe pour rejoindre l’espace d’administration Allo Vaho.' : 'Les comptes administrateurs sont créés uniquement sur invitation d’un Super Admin.';
      this.card.hidden = !valid;
    }
  };

  const demo = A.demo((group, value) => { if (group === 'invite') check(value); });

  async function check(state) {
    A.showView(card, 'checking', { focus: false });
    await A.wait(700);
    const s = state || demo.get('invite');
    panel.set(s === 'valid');
    if (s === 'valid') { resetForm(); A.showView(card, 'form', { focus: false }); }
    else A.showView(card, s);
  }

  /* ---------- Formulaire ---------- */
  const form = $('#activate-form'), msg = $('#activate-message'), btn = $('#activate-submit');
  const name = $('#activate-name'), pwd = $('#activate-password'), confirm = $('#activate-confirm'), confirmOk = $('#confirm-ok');
  const strength = A.strength($('#pwd-strength'), pwd);

  function resetForm() {
    A.message(msg, null);
    A.setValue(name, INVITE.name); A.setValue(pwd, ''); A.setValue(confirm, '');
    [name, pwd, confirm].forEach(i => A.field.clear(i)); confirmOk.hidden = true;
    A.hidePassword(pwd); A.hidePassword(confirm);
  }
  function checkConfirm(final) {
    if (!confirm.value) { confirmOk.hidden = true; if (final) A.field.setError(confirm, 'Confirmez votre mot de passe.'); else A.field.clear(confirm); return !final ? true : false; }
    const same = confirm.value === pwd.value;
    A.field.setError(confirm, same ? '' : 'Les mots de passe ne correspondent pas.');
    confirmOk.hidden = !same;
    return same;
  }
  confirm.addEventListener('input', () => checkConfirm(false));
  pwd.addEventListener('input', () => { if (confirm.value) checkConfirm(false); if (pwd.getAttribute('aria-invalid') === 'true' && strength.valid()) A.field.clear(pwd); });

  const lock = on => { [name, pwd, confirm].forEach(i => A.field.setDisabled(i, on)); };

  form.addEventListener('submit', async e => {
    e.preventDefault();
    A.message(msg, null);
    let first = null;
    if (name.value.trim().length < 2) { A.field.setError(name, 'Indiquez votre nom complet.'); first = name; } else A.field.clear(name);
    if (!pwd.value) { A.field.setError(pwd, 'Choisissez un mot de passe.'); first = first || pwd; }
    else if (!strength.valid()) { A.field.setError(pwd, 'Le mot de passe ne respecte pas encore toutes les règles.'); first = first || pwd; }
    else A.field.clear(pwd);
    if (!checkConfirm(true)) first = first || confirm;
    if (first) { first.focus(); return; }

    A.setLoading(btn, true, 'Activation…'); lock(true);
    await A.wait(1200);
    A.setLoading(btn, false); lock(false);

    if (demo.get('activation') === 'server') {
      A.message(msg, { type: 'error', title: 'Une erreur est survenue. Réessayez.', text: 'Votre compte n’a pas été activé. Vos informations sont conservées.', code: 'ACT-500' });
      btn.focus(); return;
    }
    $('[data-success-name]').textContent = name.value.trim();
    $('#success-login').href = 'login.html?email=' + encodeURIComponent(INVITE.email);
    A.showView(card, 'success');
  });

  /* ---------- Invitation expirée : nouvelle demande (simulée) ---------- */
  $('#request-invite').addEventListener('click', async e => {
    const b = e.currentTarget;
    A.setLoading(b, true, 'Envoi de la demande…');
    await A.wait(900);
    A.setLoading(b, false);
    b.disabled = true; $('[data-label]', b).textContent = 'Demande envoyée';
    A.message($('#expired-message'), { type: 'success', title: 'Demande envoyée.', text: `${INVITE.invitedBy} recevra votre demande de nouvelle invitation.` });
  });

  const initial = new URLSearchParams(location.search).get('invitation');
  if (['valid', 'expired', 'invalid', 'used'].includes(initial)) demo.set('invite', initial);
  check();
})();
