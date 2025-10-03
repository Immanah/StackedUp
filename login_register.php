<?php
session_start();
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login / Register — Ozyde</title>
    <style>
        /* Minimal styles, keep your previous styles or copy-paste */
        body { font-family: Arial, sans-serif; background: #f9f9f9; margin:0; padding:0; }
        .auth-container { max-width:400px; margin:50px auto; padding:30px; background:white; border-radius:10px; box-shadow:0 5px 15px rgba(0,0,0,0.1);}
        .form-group { margin-bottom:15px; }
        label { display:block; margin-bottom:5px; }
        input { width:100%; padding:10px; border:1px solid #ccc; border-radius:5px; }
        button { width:100%; padding:10px; background:#111; color:white; border:none; border-radius:5px; cursor:pointer; }
        .toggle-link { color:blue; cursor:pointer; text-decoration:underline; }
        .auth-toggle { display:flex; justify-content:space-around; margin-bottom:20px; }
        .auth-toggle div { cursor:pointer; padding:10px; flex:1; text-align:center; }
        .auth-toggle .active { font-weight:bold; border-bottom:2px solid #111; }
        .auth-panel { display:none; }
        .auth-panel.active { display:block; }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-toggle">
            <div class="toggle-option active" data-target="login">Login</div>
            <div class="toggle-option" data-target="register">Register</div>
        </div>

        <!-- LOGIN FORM -->
        <div class="auth-panel active" id="login-panel">
            <form id="login-form">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit">Login</button>
            </form>
            <p>Don't have an account? <span class="toggle-link" data-target="register">Register</span></p>
        </div>

        <!-- REGISTER FORM -->
        <div class="auth-panel" id="register-panel">
            <form id="register-form">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" name="first_name" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" name="last_name" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit">Register</button>
            </form>
            <p>Already have an account? <span class="toggle-link" data-target="login">Login</span></p>
        </div>
    </div>

    <script>
        // Toggle login/register panels
        const toggleOptions = document.querySelectorAll('.toggle-option');
        const toggleLinks = document.querySelectorAll('.toggle-link');
        const panels = document.querySelectorAll('.auth-panel');

        function switchPanel(target) {
            toggleOptions.forEach(opt => opt.classList.toggle('active', opt.dataset.target === target));
            panels.forEach(panel => panel.classList.toggle('active', panel.id === `${target}-panel`));
        }

        toggleOptions.forEach(opt => opt.addEventListener('click', () => switchPanel(opt.dataset.target)));
        toggleLinks.forEach(link => link.addEventListener('click', () => switchPanel(link.dataset.target)));

        // Login handler
        const loginForm = document.getElementById('login-form');
        loginForm.addEventListener('submit', function(e){
            e.preventDefault();
            const formData = new FormData(loginForm);
            formData.append('action','login');
            fetch('auth.php', { method:'POST', body: formData })
            .then(res=>res.json())
            .then(data=>{
                if(data.status==='success'){
                    window.location.href = data.redirect; // redirect back to original page
                }else{
                    alert(data.message);
                }
            });
        });

        // Register handler
        const registerForm = document.getElementById('register-form');
        registerForm.addEventListener('submit', function(e){
            e.preventDefault();
            const formData = new FormData(registerForm);
            formData.append('action','register');
            fetch('auth.php', { method:'POST', body: formData })
            .then(res=>res.json())
            .then(data=>{
                if(data.status==='success'){
                    window.location.href = data.redirect; // redirect back to original page
                }else{
                    alert(data.message);
                }
            });
        });
    </script>
</body>
</html>
