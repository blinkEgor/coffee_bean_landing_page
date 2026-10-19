document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('lead-form');
    const btn = document.getElementById('submit-btn');
    const status = document.getElementById('form-status');
    const phoneInput = document.getElementById('phone');
    
    // Сбор UTM
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('utm_source')) document.getElementById('utm_source').value = urlParams.get('utm_source');
    if (urlParams.has('utm_medium')) document.getElementById('utm_medium').value = urlParams.get('utm_medium');

    // Маска телефона (базовая)
    phoneInput.addEventListener('input', (e) => {
        let val = e.target.value.replace(/\D/g, '');
        if (val.startsWith('375')) val = val.substring(3);
        if (val.length > 9) val = val.substring(0, 9);
        let formatted = '+375 ';
        if (val.length > 0) formatted += '(' + val.substring(0, 2);
        if (val.length > 2) formatted += ') ' + val.substring(2, 5);
        if (val.length > 5) formatted += '-' + val.substring(5, 7);
        if (val.length > 7) formatted += '-' + val.substring(7, 9);
        e.target.value = formatted;
    });

    async function initForm() {
        try {
            const res = await fetch('/api/token');
            const data = await res.json();
            if (data.ok) {
                document.getElementById('csrf_token').value = data.token;
                document.getElementById('form_ts').value = Math.floor(Date.now() / 1000);
                document.getElementById('submission_id').value = crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => (Math.random() * 16 | 0).toString(16));
            } else { throw new Error(); }
        } catch (e) {
            btn.disabled = true; status.textContent = 'Ошибка инициализации. Обновите страницу.'; status.style.color = 'red';
        }
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        // Жесткая проверка телефона перед отправкой
        const phoneDigits = phoneInput.value.replace(/\D/g, '');
        if (phoneDigits.length !== 12 || !phoneInput.value.startsWith('+375')) {
            document.getElementById('error-phone').textContent = 'Введите корректный белорусский номер';
            return;
        }
        
        btn.disabled = true; btn.textContent = 'Отправка...'; status.textContent = '';
        document.querySelectorAll('.error').forEach(el => el.textContent = '');

        try {
            const res = await fetch('/api/send', { method: 'POST', body: new FormData(form) });
            const data = await res.json();

            if (data.ok) {
                status.textContent = 'Заявка отправлена! Мы свяжемся с вами.'; status.style.color = 'green';
                form.reset(); initForm();
                ['name', 'phone', 'message'].forEach(id => sessionStorage.removeItem('form_' + id));
            } else {
                if (data.error === 'validation' && data.fields) {
                    for (const [field, msg] of Object.entries(data.fields)) {
                        const errEl = document.getElementById('error-' + field);
                        if (errEl) errEl.textContent = msg;
                    }
                    status.textContent = 'Проверьте заполнение.'; btn.disabled = false; btn.textContent = 'Отправить';
                } else if (data.error === 'rate_limit' || data.error === 'duplicate') {
                    status.textContent = 'Слишком много заявок. Подождите 10 минут.'; btn.textContent = 'Заблокировано';
                    setTimeout(() => { btn.disabled = false; btn.textContent = 'Отправить'; }, 600000);
                } else {
                    status.textContent = 'Ошибка сервера. Попробуйте позже.'; btn.disabled = false; btn.textContent = 'Отправить';
                }
                status.style.color = 'red';
            }
        } catch (err) {
            status.textContent = 'Нет связи. Проверьте интернет.'; status.style.color = 'red'; btn.disabled = false; btn.textContent = 'Отправить';
        }
    });

    // Восстановление сессии
    ['name', 'phone', 'message'].forEach(id => {
        const el = document.getElementById(id); if (!el) return;
        const saved = sessionStorage.getItem('form_' + id); if (saved) el.value = saved;
        el.addEventListener('input', () => sessionStorage.setItem('form_' + id, el.value));
    });

    initForm();
});
