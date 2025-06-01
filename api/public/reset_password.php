<?php
// Incluir la configuración del servidor
require_once '../config/server.php'; // Ajusta la ruta si es necesario
?>

<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Restablecer Contraseña - COBAN365</title>

  <!-- Fuentes de Google -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat&family=Roboto&display=swap" rel="stylesheet" />

  <style>
    :root {
      --color-primary: #1a1a1a;
      --color-secondary: #4d4d4d;
      --color-background: #ffffff;
      --color-background-grey: #f2f2f2;
      --color-text: #1a1a1a;
      --color-text-white: #ffffff;

      --font-main: "Roboto", sans-serif;
      --font-heading: "Montserrat", sans-serif;
    }

    body {
      font-family: var(--font-main);
      background: var(--color-background-grey);
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
      color: var(--color-text);
    }

    .container {
      background: var(--color-background);
      padding: 2.5rem 2rem;
      border-radius: 12px;
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
      max-width: 420px;
      width: 100%;
      box-sizing: border-box;
      text-align: center;
    }

    h2 {
      font-family: var(--font-heading);
      color: var(--color-primary);
      margin-bottom: 1.5rem;
      font-weight: 700;
    }

    input[type="password"] {
      width: 100%;
      padding: 12px 14px;
      margin-top: 1rem;
      border-radius: 6px;
      border: 1.5px solid var(--color-secondary);
      font-size: 1rem;
      transition: border-color 0.3s ease;
      box-sizing: border-box;
    }

    input[type="password"]:focus {
      outline: none;
      border-color: var(--color-primary);
      box-shadow: 0 0 5px var(--color-primary);
    }

    button {
      width: 100%;
      padding: 12px 0;
      margin-top: 1.8rem;
      border-radius: 6px;
      border: none;
      background-color: var(--color-secondary);
      color: var(--color-text-white);
      font-weight: 700;
      font-size: 1.1rem;
      cursor: pointer;
      transition: background-color 0.3s ease;
      font-family: var(--font-heading);
      user-select: none;
    }

    button:hover:not(:disabled) {
      background-color: var(--color-primary);
    }

    button:disabled {
      background-color: #999999;
      cursor: not-allowed;
    }

    .message {
      margin-top: 1rem;
      font-weight: 600;
      font-size: 1rem;
    }

    .message.error {
      color: #d32f2f;
    }

    .message.success {
      color: #388e3c;
    }

    #goHomeBtn {
      margin-top: 1.5rem;
      background-color: var(--color-primary);
      color: var(--color-text-white);
      font-size: 1rem;
      padding: 10px 0;
      border-radius: 6px;
      border: none;
      cursor: pointer;
      font-family: var(--font-heading);
      transition: background-color 0.3s ease;
      width: 100%;
    }

    #goHomeBtn:hover {
      background-color: var(--color-secondary);
    }
  </style>
</head>

<body>
  <div class="container">
    <h2>Restablecer Contraseña</h2>
    <form id="resetForm" novalidate>
      <input type="password" id="new_password" placeholder="Nueva contraseña" required minlength="6"
        autocomplete="new-password" />
      <input type="password" id="confirm_password" placeholder="Confirmar nueva contraseña" required minlength="6"
        autocomplete="new-password" />
      <button type="submit" id="submitBtn">Restablecer</button>
    </form>
    <div class="message" id="message"></div>
  </div>

  <script>
    const baseUrl = "<?php echo BASE_URL_FRONT; ?>";
    const baseApiUrl = "<?php echo BASE_URL_API; ?>";

    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get("token");
    const email = urlParams.get("email");

    const form = document.getElementById("resetForm");
    const message = document.getElementById("message");
    const submitBtn = document.getElementById("submitBtn");
    const newPasswordInput = document.getElementById("new_password");
    const confirmPasswordInput = document.getElementById("confirm_password");

    if (!token || !email) {
      form.innerHTML =
        "<p class='message error'>Token inválido o incompleto.</p>";
    }

    form.addEventListener("submit", async (e) => {
      e.preventDefault();

      const newPassword = newPasswordInput.value.trim();
      const confirmPassword = confirmPasswordInput.value.trim();

      if (newPassword.length < 6) {
        showMessage(
          "La contraseña debe tener al menos 6 caracteres.",
          "error"
        );
        return;
      }
      if (newPassword !== confirmPassword) {
        showMessage("Las contraseñas no coinciden.", "error");
        return;
      }

      submitBtn.disabled = true;
      showMessage("Procesando...", "");

      try {
        const res = await fetch(`${baseApiUrl}/auth/reset_password.php`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ email, token, new_password: newPassword }),
        });
        const result = await res.json();

        if (result.success) {
          showMessage(result.message, "success");
          form.style.display = "none";

          const goHomeBtn = document.createElement("button");
          goHomeBtn.id = "goHomeBtn";
          goHomeBtn.textContent = "Ir a la página de inicio";
          goHomeBtn.onclick = () => {
            window.location.href = baseUrl;
          };
          document.querySelector(".container").appendChild(goHomeBtn);
        } else {
          showMessage(result.message, "error");
        }
      } catch (error) {
        showMessage("Error en la comunicación con el servidor.", "error");
      } finally {
        submitBtn.disabled = false;
      }
    });

    function showMessage(msg, type) {
      message.textContent = msg;
      message.className = "message " + (type || "");
    }
  </script>
</body>

</html>