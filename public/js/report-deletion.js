(function (window) {
    'use strict';

    const validate = value => {
        const reason = String(value ?? '').trim();
        if (!reason) return 'Indique el motivo de eliminación.';
        if (Array.from(reason).length > 1000) return 'El motivo no puede superar los 1000 caracteres.';
        return null;
    };

    window.SiaReportDeletion = {
        async requestReason({title, text}) {
            if (typeof window.Swal !== 'undefined') {
                const result = await window.Swal.fire({
                    icon: 'warning', title, text,
                    input: 'textarea',
                    inputLabel: 'Motivo de eliminación (obligatorio)',
                    inputPlaceholder: 'Explique por qué necesita eliminar esta información.',
                    inputAttributes: {maxlength: '1000', 'aria-label': 'Motivo de eliminación'},
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#b42318',
                    inputValidator: validate,
                });
                if (!result.isConfirmed || validate(result.value)) return null;
                return String(result.value).trim();
            }

            let reason = '';
            while (true) {
                const value = window.prompt(`${title}\n${text}\nMotivo de eliminación (obligatorio):`, reason);
                if (value === null) return null;
                reason = value.trim();
                const error = validate(reason);
                if (!error) return reason;
                window.alert(error);
            }
        },
    };
})(window);
