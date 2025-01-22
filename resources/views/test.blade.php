<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google-signin-client_id" content="1079597346970-uc517pkj6kc59qts9pqor15p7hcfv2aa.apps.googleusercontent.com">

    <title>Document</title>
</head>
<body>
    <div id="g_id_onload"
        data-client_id="1079597346970-uc517pkj6kc59qts9pqor15p7hcfv2aa.apps.googleusercontent.com"
        data-context="signin"
        data-ux_mode="popup"
        data-login_uri="https://sandbox-api-pinvest.kampuskita.co"
        data-auto_prompt="false"
        data-callback="onSignIn">
    </div>

    <div class="g_id_signin"
        data-type="standard"
        data-shape="rectangular"
        data-theme="outline"
        data-text="signin_with"
        data-size="large"
        data-locale="en-US"
        data-logo_alignment="left">
    </div>

    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script>
        function parseJwt (token) {
            var base64Url = token.split('.')[1];
            var base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
            var jsonPayload = decodeURIComponent(window.atob(base64).split('').map(function(c) {
                return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
            }).join(''));

            return JSON.parse(jsonPayload);
        }

        function onSignIn(response) {
            console.log(response);
            console.log(parseJwt(response.credential));
        }
    </script>
</body>
</html>