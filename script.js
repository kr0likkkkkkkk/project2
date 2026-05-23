document.addEventListener('DOMContentLoaded', function() {
    const mainForm = document.getElementById('mainForm');
    const loginForm = document.getElementById('loginForm');
    const messageDiv = document.createElement('div');
    messageDiv.id = 'api-message';
    messageDiv.className = '';
    messageDiv.style.display = 'none';
    
    if (mainForm) {
        mainForm.insertBefore(messageDiv, mainForm.firstChild);
        
        mainForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(mainForm);
            const data = {};
            
            formData.forEach((value, key) => {
                if (key === 'languages[]') {
                    if (!data.languages) data.languages = [];
                    data.languages.push(value);
                } else if (key !== 'csrf_token' && key !== 'edit_id') {
                    data[key] = value;
                }
            });
            
            if (data.languages && !Array.isArray(data.languages)) {
                data.languages = [data.languages];
            }
            
            const method = EDIT_ID ? 'PUT' : 'POST';
            const url = EDIT_ID ? API_URL + '/' + EDIT_ID : API_URL;
            
            try {
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showMessage(result.message, 'success');
                    mainForm.reset();
                    
                    if (result.login && result.password) {
                        showCredentials(result.login, result.password);
                    }
                    
                    if (result.profile_url) {
                        setTimeout(() => {
                            window.location.href = result.profile_url;
                        }, 2000);
                    } else if (EDIT_ID) {
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    }
                } else {
                    showErrors(result.errors);
                }
            } catch (error) {
                console.error('Ошибка:', error);
                showMessage('Ошибка соединения с сервером. Отправка через обычную форму...', 'error');
                setTimeout(() => {
                    mainForm.submit();
                }, 1000);
            }
        });
    }
    
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const login = document.getElementById('login')?.value;
            const password = document.getElementById('password')?.value;
            
            if (!login || !password) {
                e.preventDefault();
                showMessage('Введите логин и пароль', 'error');
            }
        });
    }
    
    function showMessage(text, type) {
        messageDiv.textContent = text;
        messageDiv.className = type === 'success' ? 'success-message' : 'error-message';
        messageDiv.style.display = 'block';
        
        setTimeout(() => {
            messageDiv.style.display = 'none';
        }, 5000);
    }
    
    function showErrors(errors) {
        const errorFields = ['full_name', 'phone', 'email', 'birth_date', 'gender', 'languages', 'biography', 'agreed_to_contract'];
        
        for (const field of errorFields) {
            const errorSpan = document.getElementById(`error-${field}`);
            if (errorSpan) {
                errorSpan.textContent = '';
            }
            const inputField = document.getElementById(field);
            if (inputField) {
                inputField.classList.remove('error-field');
            }
        }
        
        let errorList = '<ul>';
        for (const [field, error] of Object.entries(errors)) {
            errorList += `<li>${escapeHtml(error)}</li>`;
            
            const errorSpan = document.getElementById(`error-${field}`);
            if (errorSpan) {
                errorSpan.textContent = error;
            }
            const inputField = document.getElementById(field);
            if (inputField) {
                inputField.classList.add('error-field');
            }
            if (field === 'gender') {
                const radios = document.querySelectorAll('input[name="gender"]');
                radios.forEach(radio => {
                    radio.closest('.radio-group')?.classList.add('error-field');
                });
            }
            if (field === 'languages') {
                const select = document.getElementById('languages');
                if (select) select.classList.add('error-field');
            }
            if (field === 'agreed_to_contract') {
                const checkbox = document.getElementById('agreed_to_contract');
                if (checkbox) checkbox.closest('.checkbox-group')?.classList.add('error-field');
            }
        }
        errorList += '</ul>';
        
        showMessage(errorList, 'error');
    }
    
    function showCredentials(login, password) {
        const credsDiv = document.createElement('div');
        credsDiv.className = 'credentials-message';
        credsDiv.innerHTML = `
            <strong>Сохраните ваши данные для входа!</strong><br>
            Логин: <strong>${escapeHtml(login)}</strong><br>
            Пароль: <strong>${escapeHtml(password)}</strong><br>
            <small>Для редактирования данных используйте эти данные в форме входа ниже.</small>
        `;
        
        const formContent = document.querySelector('.form-content');
        if (formContent) {
            formContent.insertBefore(credsDiv, formContent.firstChild);
            
            setTimeout(() => {
                credsDiv.remove();
            }, 10000);
        }
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});