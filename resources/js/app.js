document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('[data-sidebar]');
    const overlay = document.querySelector('[data-sidebar-overlay]');
    const toggles = document.querySelectorAll('[data-sidebar-toggle]');

    if (sidebar && overlay && toggles.length > 0) {
        const closeSidebar = () => {
            sidebar.classList.remove('sidebar--open');
            overlay.classList.remove('sidebar-overlay--visible');
            document.body.classList.remove('sidebar-open');
            toggles.forEach((toggle) => {
                toggle.setAttribute('aria-expanded', 'false');
            });
        };

        const openSidebar = () => {
            sidebar.classList.add('sidebar--open');
            overlay.classList.add('sidebar-overlay--visible');
            document.body.classList.add('sidebar-open');
            toggles.forEach((toggle) => {
                toggle.setAttribute('aria-expanded', 'true');
            });
        };

        toggles.forEach((toggle) => {
            toggle.addEventListener('click', () => {
                if (sidebar.classList.contains('sidebar--open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });
        });

        overlay.addEventListener('click', closeSidebar);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && sidebar.classList.contains('sidebar--open')) {
                closeSidebar();
            }
        });
    }

    // Mobile filter drawer: the .filter-chips form (x-filter-drawer's slot) lives
    // in a panel that slides in from the right instead of wrapping inline. One
    // instance per page, same open/close idiom as the sidebar above.
    const filterDrawer = document.querySelector('[data-filter-drawer]');
    const filterDrawerOverlay = document.querySelector('[data-filter-drawer-overlay]');
    const filterDrawerToggles = document.querySelectorAll('[data-filter-drawer-toggle]');
    const filterDrawerClose = document.querySelector('[data-filter-drawer-close]');

    if (filterDrawer && filterDrawerOverlay && filterDrawerToggles.length > 0) {
        const closeFilterDrawer = () => {
            filterDrawer.classList.remove('filter-drawer--open');
            filterDrawerOverlay.classList.remove('filter-drawer-overlay--visible');
            document.body.classList.remove('filter-drawer-open');
            filterDrawerToggles.forEach((toggle) => {
                toggle.setAttribute('aria-expanded', 'false');
            });
        };

        const openFilterDrawer = () => {
            filterDrawer.classList.add('filter-drawer--open');
            filterDrawerOverlay.classList.add('filter-drawer-overlay--visible');
            document.body.classList.add('filter-drawer-open');
            filterDrawerToggles.forEach((toggle) => {
                toggle.setAttribute('aria-expanded', 'true');
            });
        };

        filterDrawerToggles.forEach((toggle) => {
            toggle.addEventListener('click', () => {
                if (filterDrawer.classList.contains('filter-drawer--open')) {
                    closeFilterDrawer();
                } else {
                    openFilterDrawer();
                }
            });
        });

        filterDrawerOverlay.addEventListener('click', closeFilterDrawer);
        filterDrawerClose?.addEventListener('click', closeFilterDrawer);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && filterDrawer.classList.contains('filter-drawer--open')) {
                closeFilterDrawer();
            }
        });
    }

    document.querySelectorAll('[data-nav-select]').forEach((select) => {
        select.addEventListener('change', () => {
            const destination = select.value;

            if (destination) {
                window.location.assign(destination);
            }
        });
    });

    document.querySelectorAll('[data-toggle-password]').forEach((button) => {
        const input = document.getElementById(button.dataset.togglePassword);
        const icon = button.querySelector('.material-symbols-outlined');

        if (!input || !icon) {
            return;
        }

        button.addEventListener('click', () => {
            const isHidden = input.type === 'password';

            input.type = isHidden ? 'text' : 'password';
            icon.textContent = isHidden ? 'visibility_off' : 'visibility';
            button.setAttribute('aria-pressed', String(isHidden));
            button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
    });

    const closeAllDropdowns = (except = null) => {
        document.querySelectorAll('[data-dropdown].dropdown--open').forEach((dropdown) => {
            if (dropdown === except) {
                return;
            }

            const trigger = dropdown.querySelector('[data-dropdown-trigger]');
            const menu = dropdown.querySelector('[data-dropdown-menu]');

            dropdown.classList.remove('dropdown--open');
            menu?.setAttribute('hidden', '');
            trigger?.setAttribute('aria-expanded', 'false');
            dropdown.querySelectorAll('[data-dropdown-option]').forEach((option) => {
                option.classList.remove('dropdown__option--active');
            });
        });
    };

    const openDropdown = (dropdown) => {
        const trigger = dropdown.querySelector('[data-dropdown-trigger]');
        const menu = dropdown.querySelector('[data-dropdown-menu]');
        const selected = dropdown.querySelector('[data-dropdown-option][aria-selected="true"]');

        closeAllDropdowns(dropdown);
        dropdown.classList.add('dropdown--open');
        menu?.removeAttribute('hidden');
        trigger?.setAttribute('aria-expanded', 'true');

        if (selected) {
            selected.classList.add('dropdown__option--active');
            selected.scrollIntoView({ block: 'nearest' });
        }
    };

    const closeDropdown = (dropdown) => {
        const trigger = dropdown.querySelector('[data-dropdown-trigger]');
        const menu = dropdown.querySelector('[data-dropdown-menu]');

        dropdown.classList.remove('dropdown--open');
        menu?.setAttribute('hidden', '');
        trigger?.setAttribute('aria-expanded', 'false');
        dropdown.querySelectorAll('[data-dropdown-option]').forEach((option) => {
            option.classList.remove('dropdown__option--active');
        });
    };

    const selectDropdownOption = (dropdown, option) => {
        const native = dropdown.querySelector('[data-dropdown-native]');
        const valueEl = dropdown.querySelector('[data-dropdown-value]');
        const placeholder = dropdown.dataset.placeholder ?? '';
        const value = option.dataset.value ?? '';
        const label = option.querySelector('.dropdown__option-label')?.textContent?.trim() ?? '';

        dropdown.querySelectorAll('[data-dropdown-option]').forEach((item) => {
            const isSelected = item === option;
            item.setAttribute('aria-selected', String(isSelected));
            item.classList.toggle('dropdown__option--selected', isSelected);
        });

        if (native) {
            native.value = value;
            native.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (valueEl) {
            valueEl.textContent = label;
            valueEl.classList.toggle('dropdown__value--placeholder', value === '' && label === placeholder);
        }

        closeDropdown(dropdown);
        dropdown.querySelector('[data-dropdown-trigger]')?.focus();
    };

    const getActiveOption = (dropdown) => {
        return dropdown.querySelector('[data-dropdown-option].dropdown__option--active')
            ?? dropdown.querySelector('[data-dropdown-option][aria-selected="true"]')
            ?? dropdown.querySelector('[data-dropdown-option]');
    };

    const moveActiveOption = (dropdown, direction) => {
        const options = [...dropdown.querySelectorAll('[data-dropdown-option]')];

        if (options.length === 0) {
            return;
        }

        const current = getActiveOption(dropdown);
        const currentIndex = options.indexOf(current);
        let nextIndex = currentIndex + direction;

        if (nextIndex < 0) {
            nextIndex = options.length - 1;
        }

        if (nextIndex >= options.length) {
            nextIndex = 0;
        }

        options.forEach((option) => option.classList.remove('dropdown__option--active'));
        options[nextIndex].classList.add('dropdown__option--active');
        options[nextIndex].scrollIntoView({ block: 'nearest' });
    };

    document.querySelectorAll('[data-dropdown]').forEach((dropdown) => {
        const trigger = dropdown.querySelector('[data-dropdown-trigger]');
        const menu = dropdown.querySelector('[data-dropdown-menu]');

        if (!trigger || !menu) {
            return;
        }

        trigger.addEventListener('click', () => {
            if (dropdown.classList.contains('dropdown--open')) {
                closeDropdown(dropdown);
            } else {
                openDropdown(dropdown);
            }
        });

        menu.querySelectorAll('[data-dropdown-option]').forEach((option) => {
            option.addEventListener('click', () => {
                selectDropdownOption(dropdown, option);
            });
        });

        const native = dropdown.querySelector('[data-dropdown-native]');

        if (native) {
            native.addEventListener('invalid', (event) => {
                event.preventDefault();
                trigger.classList.add('dropdown__trigger--invalid');
                trigger.focus();

                if (! dropdown.classList.contains('dropdown--open')) {
                    openDropdown(dropdown);
                }
            });

            native.addEventListener('change', () => {
                trigger.classList.remove('dropdown__trigger--invalid');
            });
        }

        trigger.addEventListener('keydown', (event) => {
            const isOpen = dropdown.classList.contains('dropdown--open');

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();

                if (!isOpen) {
                    openDropdown(dropdown);
                }

                moveActiveOption(dropdown, event.key === 'ArrowDown' ? 1 : -1);
            }

            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();

                if (!isOpen) {
                    openDropdown(dropdown);
                    return;
                }

                const active = getActiveOption(dropdown);

                if (active) {
                    selectDropdownOption(dropdown, active);
                }
            }

            if (event.key === 'Escape' && isOpen) {
                event.preventDefault();
                closeDropdown(dropdown);
            }

            if (event.key === 'Home' && isOpen) {
                event.preventDefault();
                const first = dropdown.querySelector('[data-dropdown-option]');
                dropdown.querySelectorAll('[data-dropdown-option]').forEach((option) => {
                    option.classList.remove('dropdown__option--active');
                });
                first?.classList.add('dropdown__option--active');
                first?.scrollIntoView({ block: 'nearest' });
            }

            if (event.key === 'End' && isOpen) {
                event.preventDefault();
                const options = dropdown.querySelectorAll('[data-dropdown-option]');
                const last = options[options.length - 1];
                options.forEach((option) => option.classList.remove('dropdown__option--active'));
                last?.classList.add('dropdown__option--active');
                last?.scrollIntoView({ block: 'nearest' });
            }
        });
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-dropdown]')) {
            closeAllDropdowns();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAllDropdowns();
        }
    });

    const closeAllNotificationPanels = (except = null) => {
        document.querySelectorAll('[data-notification-panel].topbar__notifications--open').forEach((panel) => {
            if (panel === except) {
                return;
            }

            const toggle = panel.querySelector('[data-notification-toggle]');
            const menu = panel.querySelector('[data-notification-menu]');

            panel.classList.remove('topbar__notifications--open');
            menu?.setAttribute('hidden', '');
            toggle?.setAttribute('aria-expanded', 'false');
        });
    };

    document.querySelectorAll('[data-notification-panel]').forEach((panel) => {
        const toggle = panel.querySelector('[data-notification-toggle]');
        const menu = panel.querySelector('[data-notification-menu]');

        if (!toggle || !menu) {
            return;
        }

        toggle.addEventListener('click', () => {
            const isOpen = panel.classList.contains('topbar__notifications--open');

            closeAllNotificationPanels(panel);
            closeAllDropdowns();

            if (isOpen) {
                panel.classList.remove('topbar__notifications--open');
                menu.setAttribute('hidden', '');
                toggle.setAttribute('aria-expanded', 'false');
            } else {
                panel.classList.add('topbar__notifications--open');
                menu.removeAttribute('hidden');
                toggle.setAttribute('aria-expanded', 'true');
            }
        });
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-notification-panel]')) {
            closeAllNotificationPanels();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAllNotificationPanels();
        }
    });
});
