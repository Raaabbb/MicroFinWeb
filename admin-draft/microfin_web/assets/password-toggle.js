document.addEventListener('DOMContentLoaded', () => {
    const renderPasswordIcon = (isVisible) => {
        if (isVisible) {
            return `
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M3 3l18 18"></path>
                    <path d="M10.58 10.58a2 2 0 0 0 2.83 2.83"></path>
                    <path d="M9.88 5.09A10.94 10.94 0 0 1 12 4.91c5.06 0 9.27 3.11 10.5 7.09a11.8 11.8 0 0 1-4.04 5.65"></path>
                    <path d="M6.61 6.61A11.84 11.84 0 0 0 1.5 12c.9 2.91 3.36 5.33 6.48 6.49"></path>
                </svg>
            `;
        }

        return `
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M1.5 12S5.5 5 12 5s10.5 7 10.5 7-4 7-10.5 7S1.5 12 1.5 12Z"></path>
                <circle cx="12" cy="12" r="3"></circle>
            </svg>
        `;
    };

    const passwordInputs = Array.from(document.querySelectorAll('input[type="password"]'))
        .filter((input) => !input.hasAttribute('data-password-toggle-bound') && !input.disabled);

    passwordInputs.forEach((input) => {
        const parent = input.parentNode;
        if (!parent) {
            return;
        }

        input.setAttribute('data-password-toggle-bound', 'true');

        const wrapper = document.createElement('div');
        wrapper.className = 'password-toggle-wrap';

        parent.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        const toggleButton = document.createElement('button');
        toggleButton.type = 'button';
        toggleButton.className = 'password-toggle-btn';
        toggleButton.setAttribute('aria-label', 'Show password');
        toggleButton.setAttribute('title', 'Show password');
        toggleButton.innerHTML = renderPasswordIcon(false);

        toggleButton.addEventListener('click', () => {
            const shouldReveal = input.type === 'password';
            input.type = shouldReveal ? 'text' : 'password';
            toggleButton.setAttribute('aria-label', shouldReveal ? 'Hide password' : 'Show password');
            toggleButton.setAttribute('title', shouldReveal ? 'Hide password' : 'Show password');
            toggleButton.innerHTML = renderPasswordIcon(shouldReveal);
            input.focus({ preventScroll: true });
            const length = input.value.length;
            if (typeof input.setSelectionRange === 'function') {
                input.setSelectionRange(length, length);
            }
        });

        wrapper.appendChild(toggleButton);
    });
});
