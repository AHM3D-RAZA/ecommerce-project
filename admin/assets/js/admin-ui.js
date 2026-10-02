/*
 * Small admin-panel UI patch.
 *
 * Material Dashboard only adds the "is-filled" state to an .input-group-outline
 * while the user is actively typing (its handlers listen for keyup / focusout),
 * so any field that is pre-filled by the server - the edit product / edit
 * category forms, or the login email after a failed attempt - keeps its
 * floating label sitting on top of the value. Put those groups into the filled
 * state on load so the label floats out of the way, exactly as it does once you
 * start typing.
 */
(function () {
    function markFilledGroups() {
        var groups = document.querySelectorAll('.input-group.input-group-outline');
        for (var i = 0; i < groups.length; i++) {
            var field = groups[i].querySelector('input, textarea, select');
            if (field && String(field.value || '').trim() !== '') {
                groups[i].classList.add('is-filled');
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', markFilledGroups);
    } else {
        markFilledGroups();
    }
})();
