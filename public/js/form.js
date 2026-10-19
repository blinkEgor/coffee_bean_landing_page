document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('lead-form');
    const btn = document.getElementById('submit-btn');
    const status = document.getElementById('form-status');
    
    const csrfInput = document.getElementById('csrf_token');
    const tsInput = document.getElementById('form_ts');
    const uuidInput = document.getElementById('submission_id');

    // 1. Инициализация: получаем CSRF и ставим метку времени
    async function initForm() {
        try {
            const res = await fetch('/api/token');
            const data = await res.json();
            if (data.ok) {
                csrfInput.value = data.token;
                // Время заполнения формы (Unix timestamp в секундах)
                tsInput.value = Math.floor(Date.now() / 1000);
                // Генерируем UUID (нативный, если браузер старый — fallback)
                uuidInput.value = crypto.randomUUID ? crypto.randomUUID() : generateUUID();
            } else {
                btn.disabled = true;
                status.textContent = 'Ошибка инициализации формы. Обновите страницу.';
            }
        } catch (e) {
            btn.disabled = true;
            status.textContent = 'Нет связи с сервером. Обновите страницу.';
        }
    }

    // 2. Отправка
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        btn.disabled = true;
        btn.textContent = 'Отправка...';
        status.textContent = '';
        
        // Очистка старых ошибок
        document.querySelectorAll('.error').forEach(el => el.textContent = '');

        const formData = new FormData(form);

        try {
            const res = await fetch('/api/send', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.ok) {
                status.textContent = 'Заявка успешно отправлена!';
                status.style.color = 'green';
                form.reset();
                // Переинициализируем токен и UUID для следующей заявки
                initForm(); 
            } else {
                // Обработка ошибок валидации
                if (data.error === 'validation' && data.fields) {
                    for (const [field, msg] of Object.entries(data.fields)) {
                        const errEl = document.getElementById('error-' + field);
                        if (errEl) errEl.textContent = msg;
                    }
                    status.textContent = 'Проверьте правильность заполнения.';
                    
                    // Если ошибка валидации — разрешаем исправить и отправить снова
                    btn.disabled = false;
                    btn.textContent = 'Отправить';
                } 
                else if (data.error === 'too_fast') {
                    status.textContent = 'Слишком быстро. Попробуйте ещё раз.';
                    btn.disabled = false;
                    btn.textContent = 'Отправить';
                } 
                else if (data.error === 'rate_limit') {
                    status.textContent = 'Слишком много заявок. Подождите 10 минут.';
                    btn.textContent = 'Заблокировано (10 мин)';
                    
                    // Разблокируем кнопку через 10 минут (600 000 мс)
                    setTimeout(() => {
                        btn.disabled = false;
                        btn.textContent = 'Отправить';
                        status.textContent = 'Можете попробовать снова.';
                        status.style.color = 'inherit';
                    }, 600000);
                } 
                else {
                    // Ошибка Телеграма или 500
                    status.textContent = 'Ошибка отправки. Попробуйте позже.';
                    btn.disabled = false;
                    btn.textContent = 'Отправить';
                }
                status.style.color = 'red';
            }
        } catch (err) {
            status.textContent = 'Сетевая ошибка. Проверьте интернет.';
            status.style.color = 'red';
            btn.disabled = false;
            btn.textContent = 'Отправить';
        }
    });

    // Fallback для UUID (если crypto.randomUUID недоступен, например по HTTP)
    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    // 3. Сохранение и восстановление введенных данных (защита от случайного F5)
    const fields = ['name', 'phone', 'message'];
    
    // Восстанавливаем при загрузке
    fields.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        const saved = sessionStorage.getItem('form_' + id);
        if (saved) el.value = saved;

        // Сохраняем при каждом изменении
        el.addEventListener('input', () => {
            sessionStorage.setItem('form_' + id, el.value);
        });
    });

    // Очищаем хранилище при успешной отправке (внутри блока if (data.ok))
    // Вставь это туда:
    // fields.forEach(id => sessionStorage.removeItem('form_' + id));

    // Запуск
    initForm();
});
