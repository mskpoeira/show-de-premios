const form = document.getElementById("presenca-form");
const phone = document.getElementById("telefone");
const button = document.getElementById("submit");
const message = document.getElementById("message");

phone.addEventListener("input", () => {
  const d = phone.value.replace(/\D/g, "").slice(0, 11);
  if (d.length <= 2) phone.value = d;
  else if (d.length <= 6) phone.value = `(${d.slice(0,2)}) ${d.slice(2)}`;
  else if (d.length <= 10) phone.value = `(${d.slice(0,2)}) ${d.slice(2,6)}-${d.slice(6)}`;
  else phone.value = `(${d.slice(0,2)}) ${d.slice(2,7)}-${d.slice(7)}`;
});

form.addEventListener("submit", async (event) => {
  event.preventDefault();
  message.className = "message";
  message.textContent = "";

  if (!form.reportValidity()) return;

  button.disabled = true;
  button.textContent = "Registrando…";

  try {
    const response = await fetch("/registrar.php", {
      method: "POST",
      body: new FormData(form)
    });

    const data = await response.json();

    if (!response.ok || !data.ok) {
      throw new Error(data.error || "Não foi possível registrar.");
    }

    form.reset();
    message.className = "message ok";
    message.textContent = "✓ Presença registrada com sucesso. Obrigado!";
    document.getElementById("nome").focus();
  } catch (error) {
    message.className = "message error";
    message.textContent = error.message || "Não foi possível registrar. Tente novamente.";
  } finally {
    button.disabled = false;
    button.textContent = "Registrar presença";
  }
});
