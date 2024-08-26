<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Страница авторизации</title>
    <style>
        html, body {
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
        }
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            
        }
        .login {
            flex-basis: 500px;
            padding: 15px;
        }
        .login__form {
            display: block;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .login__label,
        .login__input {
            display: block;
            margin: auto;
        }
        .login__label,
        .login__title {
            text-align: center;
        }
        .login__input {
            margin-bottom: 10px;
            outline: none;
            padding: 5px;
        }
        .login__submit {
            text-align: center;
            background: #fff;
            padding: 5px 15px;
            border: 1px solid #000;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="login">
        <?php if (!empty($_SESSION['res']['answer'])): ?>
            <p style="color: red; text-align: center;"><?=$_SESSION['res']['answer'];?></p>
            <?php unset($_SESSION['res']);?>
        <?php endif; ?>

        <h1 class="login__title">Авторизация</h1>
        <form class="login__form" action="<?=PATH . $adminPath?>/login" method="post">
            <label class="login__label" for="login">Логин</label>
            <input class="login__input" type="text" name="login" id="login"/>
            <label class="login__label" for="password">Пароль</label>
            <input class="login__input" type="password" name="password" id="password"/>
            <input class="login__submit" type="submit" value="Войти" />
        </form>
    </div>
    <script src="<?=PATH . ADMIN_TEMPLATE?>js/framework-functions.js"></script>
    <script>
        let form = document.querySelector(".login__form");

        if (form) {
            form.addEventListener("submit", (e) => {
                if (e.isTrusted) {
                    e.preventDefault();

                    Ajax({
                        data: {
                            ajax: "token"
                        }
                    }).then(res => {
                        if (res) {
                            let html = `<input type="hidden" name="token" value="${res}" />`;
                            form.insertAdjacentHTML("beforeend", html);
                        }

                        form.submit();
                    });
                }
            });
        }
    </script>
</body>
</html>