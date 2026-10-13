/*
 * Allo Vaho — pages d'authentification du back-office.
 *
 * Comportements repris de la maquette validée (thème, champs, mot de passe,
 * code à 6 chiffres, robustesse), sans son panneau de démonstration.
 * Les gestionnaires sont délégués au document : ils survivent aux
 * rafraîchissements partiels de Livewire.
 */

/* ---------- Thème (même clé que le panneau Filament : theme) ---------- */
const root = document.documentElement;

const storedTheme = () => {
    try {
        return localStorage.getItem('theme');
    } catch (e) {
        return null;
    }
};

const applyTheme = (theme) => {
    const dark = theme === 'dark' || (theme !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);

    root.dataset.theme = dark ? 'dark' : 'light';
    root.classList.toggle('dark', dark);

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        const label = dark ? 'Activer le mode clair' : 'Activer le mode sombre';

        button.setAttribute('aria-pressed', String(dark));
        button.setAttribute('aria-label', label);
        button.title = label;
    });
};

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-theme-toggle]');

    if (toggle) {
        const theme = root.dataset.theme === 'dark' ? 'light' : 'dark';

        try {
            localStorage.setItem('theme', theme);
        } catch (e) {
            // Le choix reste valable pour la page en cours.
        }

        applyTheme(theme);

        return;
    }

    /* ---------- Afficher / masquer le mot de passe ---------- */
    const reveal = event.target.closest('[data-password-toggle]');

    if (reveal) {
        const input = document.getElementById(reveal.getAttribute('aria-controls'));
        const show = input.type === 'password';

        input.type = show ? 'text' : 'password';
        reveal.classList.toggle('is-visible', show);
        reveal.setAttribute('aria-pressed', String(show));
        reveal.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        input.focus({ preventScroll: true });
    }
});

/* ---------- Champs : état « rempli » ---------- */
const syncFilled = (input) => {
    input.closest('.av-auth-control')?.classList.toggle('is-filled', Boolean(input.value));
};

document.addEventListener('input', (event) => {
    if (event.target.matches?.('.av-auth-input')) {
        syncFilled(event.target);
    }
});

const syncAll = () => {
    applyTheme(storedTheme());
    document.querySelectorAll('.av-auth-input').forEach(syncFilled);
};

document.addEventListener('DOMContentLoaded', syncAll);
document.addEventListener('livewire:initialized', () => {
    window.Livewire.hook('morphed', syncAll);
});

/* ---------- Se souvenir de moi ----------
   Ne mémorise que l'adresse e-mail, dans ce navigateur. Aucun jeton, aucune
   session : le mot de passe et le code restent demandés à chaque connexion.
   x-data="avRememberEmail()" sur le formulaire, x-model="remember" sur la case. */
window.avRememberEmail = ({ key = 'av.admin.email', input = '#login-email' } = {}) => ({
    remember: false,

    init() {
        this.$nextTick(() => {
            const field = this.$el.querySelector(input);
            let saved = null;

            try {
                saved = localStorage.getItem(key);
            } catch (e) {
                // Stockage indisponible : la case reste simplement décochée.
            }

            if (saved && field && !field.value) {
                field.value = saved;
                field.dispatchEvent(new Event('input', { bubbles: true }));
                this.remember = true;
                this.$el.querySelector('input[type="password"]')?.focus({ preventScroll: true });
            }
        });
    },

    save() {
        const field = this.$el.querySelector(input);

        try {
            if (this.remember && field?.value.trim()) {
                localStorage.setItem(key, field.value.trim());
            } else {
                localStorage.removeItem(key);
            }
        } catch (e) {
            // Sans stockage, rien n'est mémorisé.
        }
    },
});

/* ---------- Code à 6 chiffres ----------
   x-data="avOtp({ onChange(code) {}, onComplete(code) {} })" sur .av-otp */
window.avOtp = ({ onChange = () => {}, onComplete = () => {} } = {}) => ({
    boxes: [],

    init() {
        this.boxes = Array.from(this.$el.querySelectorAll('.av-otp__box'));

        this.boxes.forEach((box, index) => {
            box.addEventListener('focus', () => setTimeout(() => box.select(), 0));

            box.addEventListener('input', () => {
                const digits = box.value.replace(/\D/g, '');

                box.value = '';

                if (digits) {
                    this.fill(index, digits); // gère aussi le remplissage automatique du code complet
                } else {
                    this.update();
                }
            });

            box.addEventListener('keydown', (event) => {
                if (event.key === 'Backspace' && !box.value && index > 0) {
                    event.preventDefault();
                    this.boxes[index - 1].value = '';
                    this.boxes[index - 1].focus();
                    this.update();
                } else if (event.key === 'ArrowLeft' && index > 0) {
                    event.preventDefault();
                    this.boxes[index - 1].focus();
                } else if (event.key === 'ArrowRight' && index < this.boxes.length - 1) {
                    event.preventDefault();
                    this.boxes[index + 1].focus();
                } else if (event.key.length === 1 && !/\d/.test(event.key) && !event.ctrlKey && !event.metaKey) {
                    event.preventDefault();
                }
            });

            box.addEventListener('paste', (event) => {
                event.preventDefault();

                const digits = (event.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, this.boxes.length);

                if (digits) {
                    this.fill(digits.length === this.boxes.length ? 0 : index, digits);
                }
            });
        });

        this.$nextTick(() => this.boxes[0]?.focus({ preventScroll: true }));
    },

    value() {
        return this.boxes.map((box) => box.value).join('');
    },

    fill(start, digits) {
        let position = start;

        for (const digit of digits) {
            if (position >= this.boxes.length) {
                break;
            }

            this.boxes[position].value = digit;
            position++;
        }

        this.boxes[Math.min(position, this.boxes.length - 1)].focus();
        this.update();
    },

    update() {
        this.boxes.forEach((box) => box.classList.toggle('is-filled', Boolean(box.value)));
        this.$el.classList.remove('is-error');

        const code = this.value();

        onChange(code);

        if (code.length === this.boxes.length) {
            onComplete(code);
        }
    },
});

/* ---------- Robustesse du mot de passe ----------
   Indication seulement : la seule règle imposée est la longueur minimale.
   x-data="avStrength({ min: 8 })" sur .av-strength, avec x-model sur le champ. */
window.avStrength = ({ min = 8 } = {}) => ({
    password: '',
    labels: ['—', 'Faible', 'Moyen', 'Bon', 'Excellent'],

    get longEnough() {
        return this.password.length >= min;
    },

    get level() {
        const value = this.password;

        if (!value) {
            return 0;
        }

        if (!this.longEnough) {
            return 1;
        }

        const variety = [/[a-zß-ÿ]/, /[A-ZÀ-Ý]/, /\d/, /[^A-Za-z0-9À-ÿ]/].filter((pattern) => pattern.test(value)).length;
        const score = (value.length >= 12 ? 1 : 0) + (value.length >= 16 ? 1 : 0) + (variety >= 3 ? 1 : 0);

        return Math.min(4, 2 + score);
    },
});
