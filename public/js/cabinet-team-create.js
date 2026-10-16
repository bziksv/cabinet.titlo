(function () {
    var lists = new WeakMap();

    function formModal(node) {
        var modal = node && node.closest ? node.closest('.modal') : null;
        if (modal && modal.querySelector('[data-team-create-form]')) {
            return modal;
        }
        var form = node && node.closest ? node.closest('[data-team-create-form]') : null;
        return form || null;
    }

    function membersOf(root) {
        var list = lists.get(root);
        if (!list) {
            list = [];
            lists.set(root, list);
        }
        return list;
    }

    function field(root, sel) {
        return root.querySelector(sel);
    }

    function userValue(userEl) {
        if (!userEl) {
            return 0;
        }
        var raw = userEl.value;
        if (window.jQuery) {
            var $el = window.jQuery(userEl);
            if ($el.data('select2')) {
                raw = $el.val();
            }
        }
        return parseInt(raw, 10) || 0;
    }

    function setSelectValue(userEl, value) {
        if (!userEl) {
            return;
        }
        userEl.value = value;
        if (window.jQuery) {
            var $el = window.jQuery(userEl);
            if ($el.data('select2')) {
                $el.val(value || null).trigger('change');
            }
        }
    }

    function setError(root, text) {
        var el = field(root, '[data-team-member-error]');
        if (!el) {
            return;
        }
        el.textContent = text || '';
        el.classList.toggle('d-none', !text);
    }

    function roleLabel(root, role) {
        var roleEl = field(root, '[data-team-member-role]');
        var opt = roleEl && roleEl.querySelector('option[value="' + role + '"]');
        return opt ? opt.textContent : role;
    }

    function syncEmpty(root, members) {
        var listEl = field(root, '[data-team-members-list]');
        var emptyEl = field(root, '[data-team-members-empty]');
        if (!listEl || !emptyEl) {
            return;
        }
        var has = members.length > 0;
        listEl.classList.toggle('d-none', !has);
        emptyEl.classList.toggle('d-none', has);
    }

    function render(root) {
        var members = membersOf(root);
        var listEl = field(root, '[data-team-members-list]');
        var hiddenEl = field(root, '[data-team-members-hidden]');
        if (!listEl || !hiddenEl) {
            return;
        }
        listEl.innerHTML = '';
        hiddenEl.innerHTML = '';
        members.forEach(function (m, idx) {
            var li = document.createElement('li');
            li.className = 'list-group-item d-flex align-items-center justify-content-between gap-2 py-2 px-3';
            var left = document.createElement('div');
            left.className = 'small';
            var strong = document.createElement('strong');
            strong.textContent = m.label;
            var span = document.createElement('span');
            span.className = 'text-secondary';
            span.textContent = ' · ' + roleLabel(root, m.role);
            left.appendChild(strong);
            left.appendChild(span);
            var rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'btn btn-sm btn-outline-danger';
            rm.setAttribute('data-team-member-remove', String(idx));
            rm.setAttribute('aria-label', 'Убрать');
            rm.textContent = '×';
            li.appendChild(left);
            li.appendChild(rm);
            listEl.appendChild(li);

            if (m.user_id) {
                var iu = document.createElement('input');
                iu.type = 'hidden';
                iu.name = 'members[' + idx + '][user_id]';
                iu.value = String(m.user_id);
                hiddenEl.appendChild(iu);
            }
            if (m.email) {
                var ie = document.createElement('input');
                ie.type = 'hidden';
                ie.name = 'members[' + idx + '][email]';
                ie.value = m.email;
                hiddenEl.appendChild(ie);
            }
            var ir = document.createElement('input');
            ir.type = 'hidden';
            ir.name = 'members[' + idx + '][role]';
            ir.value = m.role;
            hiddenEl.appendChild(ir);
        });
        syncEmpty(root, members);
    }

    function clearDraft(root) {
        var userEl = field(root, '[data-team-member-user]');
        var emailEl = field(root, '[data-team-member-email]');
        var roleEl = field(root, '[data-team-member-role]');
        setSelectValue(userEl, '');
        if (emailEl) {
            emailEl.value = '';
        }
        if (roleEl) {
            var part = roleEl.querySelector('option[value="participant"]');
            roleEl.value = part ? 'participant' : (roleEl.options[0] ? roleEl.options[0].value : '');
        }
    }

    function pushDraft(root, silentEmpty) {
        var userEl = field(root, '[data-team-member-user]');
        var emailEl = field(root, '[data-team-member-email]');
        var roleEl = field(root, '[data-team-member-role]');
        if (!userEl || !emailEl || !roleEl) {
            return false;
        }
        var userId = userValue(userEl);
        var email = (emailEl.value || '').trim();
        var role = roleEl.value || 'participant';
        if (!userId && !email) {
            if (!silentEmpty) {
                setError(root, 'Выберите человека из списка или укажите email.');
            }
            return false;
        }
        if (email && emailEl.checkValidity && !emailEl.checkValidity()) {
            setError(root, 'Укажите email целиком.');
            emailEl.reportValidity && emailEl.reportValidity();
            return false;
        }

        var label = email;
        if (userId) {
            var opt = userEl.options[userEl.selectedIndex];
            if (window.jQuery && window.jQuery(userEl).data('select2')) {
                var selected = window.jQuery(userEl).find('option:selected').get(0);
                if (selected) {
                    opt = selected;
                }
            }
            label = (opt && (opt.getAttribute('data-label') || opt.getAttribute('data-email'))) || (opt && opt.textContent.trim()) || ('#' + userId);
            if (!email && opt) {
                email = opt.getAttribute('data-email') || '';
            }
        }

        var members = membersOf(root);
        var dup = members.some(function (m) {
            return (userId && m.user_id === userId) || (email && m.email && m.email.toLowerCase() === email.toLowerCase());
        });
        if (dup) {
            setError(root, 'Этот участник уже в списке.');
            clearDraft(root);
            return true;
        }

        members.push({
            user_id: userId || null,
            email: email || null,
            role: role,
            label: label
        });
        setError(root, '');
        clearDraft(root);
        render(root);
        return true;
    }

    document.addEventListener('click', function (e) {
        var rm = e.target.closest('[data-team-member-remove]');
        if (rm) {
            var rootRm = formModal(rm);
            if (!rootRm) {
                return;
            }
            e.preventDefault();
            var idx = parseInt(rm.getAttribute('data-team-member-remove'), 10);
            var members = membersOf(rootRm);
            if (!isNaN(idx)) {
                members.splice(idx, 1);
                render(rootRm);
            }
            return;
        }

        var btn = e.target.closest('[data-team-member-add]');
        if (!btn) {
            return;
        }
        var root = formModal(btn);
        if (!root) {
            return;
        }
        e.preventDefault();
        if (!pushDraft(root)) {
            var emailEl = field(root, '[data-team-member-email]');
            if (emailEl) {
                emailEl.focus();
            }
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') {
            return;
        }
        var emailEl = e.target.closest ? e.target.closest('[data-team-member-email]') : null;
        if (!emailEl) {
            return;
        }
        var root = formModal(emailEl);
        if (!root) {
            return;
        }
        e.preventDefault();
        pushDraft(root);
    });

    document.addEventListener('submit', function (e) {
        var form = e.target && e.target.closest ? e.target.closest('[data-team-create-form]') : null;
        if (!form) {
            return;
        }
        pushDraft(formModal(form) || form, true);
    });

    document.addEventListener('hidden.bs.modal', function (e) {
        var modal = e.target;
        if (!modal || !modal.querySelector || !modal.querySelector('[data-team-create-form]')) {
            return;
        }
        lists.set(modal, []);
        clearDraft(modal);
        render(modal);
        setError(modal, '');
    });
})();
