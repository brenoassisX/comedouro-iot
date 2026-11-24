    </main>
    </div>
</div>

<script>
    // Qualquer <form data-confirmar="texto"> pede confirmação antes de enviar (usado nos botões Excluir)
    document.querySelectorAll('form[data-confirmar]').forEach(function (f) {
        f.addEventListener('submit', function (ev) {
            if (!confirm(f.dataset.confirmar)) { ev.preventDefault(); }
        });
    });
</script>
</body>
</html>
