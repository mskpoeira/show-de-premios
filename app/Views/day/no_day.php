<?php
use App\Core\View;
?>

<div class="card" style="max-width: 600px; margin: 3rem auto; text-align: center; padding: 3rem 2rem;">
    <div style="font-size: 3rem; margin-bottom: 1rem;">📅</div>
    <h2 style="margin-bottom: 0.5rem;">Nenhum Dia em Aberto</h2>
    <p style="color: var(--text-muted); margin-bottom: 2rem;">
        No momento não há nenhum dia de operação aberto no sistema. Você pode iniciar um novo dia de vendas agora mesmo.
    </p>

    <div>
        <a href="<?= View::url('dias/abrir') ?>" class="btn btn-primary" style="padding: 0.8rem 1.5rem; font-size: 1rem;">
            + Abrir Novo Dia
        </a>
    </div>
</div>
