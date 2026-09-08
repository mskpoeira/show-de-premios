<?php
use App\Core\View;
?>

<div class="card" style="max-width: 500px; margin: 4rem auto; text-align: center; padding: 3rem 2rem;">
    <div style="font-size: 3.5rem; margin-bottom: 1rem;">🔍</div>
    <h1 style="font-size: 1.8rem; margin-bottom: 0.5rem;">Página Não Encontrada</h1>
    <p style="color: var(--text-muted); margin-bottom: 2rem;">
        O endereço solicitado não existe ou foi movido dentro do Show de Prêmios.
    </p>
    <a href="<?= View::url('painel') ?>" class="btn btn-primary">
        ← Ir para o Painel
    </a>
</div>
