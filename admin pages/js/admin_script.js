const darkModeStorageKey = 'adminDarkMode';

const closeMoreMenu = (moreMenu, moreMenuButton) => {
    if (!moreMenu || !moreMenuButton) {
        return;
    }

    moreMenu.classList.remove('is-open');
    moreMenuButton.setAttribute('aria-expanded', 'false');
};

const normalizeText = (value) => value.toLowerCase().replace(/\s+/g, ' ').trim();

const filterElements = (elements, searchTerm) => {
    let visibleCount = 0;

    elements.forEach((element) => {
        const matches = searchTerm === '' || normalizeText(element.textContent).includes(searchTerm);
        element.hidden = !matches;

        if (matches) {
            visibleCount += 1;
        }
    });

    return visibleCount;
};

const getSearchTargets = (searchShell) => {
    const configuredSelector = searchShell?.dataset.adminSearchTargets || '';

    if (configuredSelector.trim() !== '') {
        return document.querySelectorAll(configuredSelector);
    }

    const path = window.location.pathname.split('/').pop();
    const targetMap = {
        'admin-orders-list.php': '.orders-list-card',
        'admin-contact-messages.php': '.contact-message-card',
        'admin-users-list.php': '.users-table tbody tr',
        'admin-analytics.php': '.analytics-stat-card, .analytics-health-item, .analytics-table tbody tr',
        'admin-home.php': '.stat-card',
        'admin-profile.php': '.profile-card, .profile-panel, .activity-card',
        'admin-settings.php': '.settings-card, .settings-panel',
        'admin-add-product.php': '.add-product-panel',
        'admin-edit-product.php': '.edit-product-panel',
        'admin-order-details.php': '.order-details-card, .order-info-card, .order-section-card, .order-summary-card',
    };

    const selector = targetMap[path];

    return selector ? document.querySelectorAll(selector) : [];
};

const setupSearch = () => {
    const searchShell = document.querySelector('.search-shell');

    if (!searchShell || searchShell.tagName.toLowerCase() === 'form') {
        return;
    }

    const searchInput = searchShell.querySelector('input[type="search"]');
    const searchButton = searchShell.querySelector('.search-submit-button, button');
    const clearButton = searchShell.querySelector('[data-admin-search-clear]');
    const status = document.querySelector('[data-admin-search-status]');
    const searchableElements = Array.from(getSearchTargets(searchShell));
    const defaultStatusText = status?.textContent || '';

    if (!searchInput || searchableElements.length === 0) {
        if (status) {
            status.textContent = 'Search is ready when this page has filterable content.';
        }
        return;
    }

    const updateStatus = (visibleCount, searchTerm) => {
        if (!status) {
            return;
        }

        if (searchTerm === '') {
            status.textContent = defaultStatusText || `${searchableElements.length} items ready to search.`;
            return;
        }

        const resultLabel = visibleCount === 1 ? 'result' : 'results';
        status.textContent = visibleCount === 0
            ? `No results for "${searchInput.value.trim()}".`
            : `${visibleCount} ${resultLabel} for "${searchInput.value.trim()}".`;
    };

    const runSearch = () => {
        const searchTerm = normalizeText(searchInput.value);
        const visibleCount = filterElements(searchableElements, searchTerm);

        if (clearButton) {
            clearButton.hidden = searchTerm === '';
        }

        searchShell.classList.toggle('has-search-value', searchTerm !== '');
        searchShell.classList.toggle('has-no-results', searchTerm !== '' && visibleCount === 0);
        updateStatus(visibleCount, searchTerm);
    };

    const clearSearch = () => {
        searchInput.value = '';
        runSearch();
        searchInput.focus();
    };

    searchInput.addEventListener('input', runSearch);
    searchInput.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && searchInput.value.trim() !== '') {
            event.preventDefault();
            clearSearch();
        }
    });

    if (searchButton) {
        searchButton.addEventListener('click', runSearch);
    }

    if (clearButton) {
        clearButton.addEventListener('click', clearSearch);
    }

    runSearch();
};

const setupNotificationCount = async () => {
    const notificationButton = document.querySelector('.topbar-actions .icon-button[aria-label="Notifications"]');

    if (!notificationButton) {
        return;
    }

    const topbarActions = notificationButton.closest('.topbar-actions');
    const notificationMenu = document.createElement('div');
    notificationMenu.className = 'notification-menu';

    if (topbarActions) {
        topbarActions.insertBefore(notificationMenu, notificationButton);
        notificationMenu.appendChild(notificationButton);
    }

    notificationButton.classList.add('notification-button');
    notificationButton.setAttribute('aria-haspopup', 'true');
    notificationButton.setAttribute('aria-expanded', 'false');

    const dropdown = document.createElement('div');
    dropdown.className = 'notification-dropdown';
    dropdown.hidden = true;
    dropdown.innerHTML = `
        <div class="notification-dropdown-header">
            <strong>Notifications</strong>
            <span class="notification-count-label">0 unread</span>
        </div>
        <div class="notification-list" role="menu"></div>
    `;
    notificationMenu.appendChild(dropdown);

    const countLabel = dropdown.querySelector('.notification-count-label');
    const notificationList = dropdown.querySelector('.notification-list');

    let badge = notificationButton.querySelector('.notification-badge');

    if (!badge) {
        badge = document.createElement('span');
        badge.className = 'notification-badge';
        notificationButton.appendChild(badge);
    }

    const updateBadge = (unreadCount) => {
        badge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
        badge.hidden = unreadCount === 0;
        countLabel.textContent = `${unreadCount} unread`;
        notificationButton.title = `${unreadCount} unread notifications`;
        notificationButton.setAttribute('aria-label', `${unreadCount} unread notifications`);
    };

    const formatDate = (value) => {
        const date = new Date(value.replace(' ', 'T'));

        if (Number.isNaN(date.getTime())) {
            return value || 'Date unavailable';
        }

        return date.toLocaleString([], {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    };

    const escapeHtml = (value) => String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const renderNotifications = (data) => {
        const unreadCount = Number(data.unread_count || 0);
        const notifications = Array.isArray(data.notifications) ? data.notifications : [];

        updateBadge(unreadCount);

        if (notifications.length === 0) {
            notificationList.innerHTML = `
                <div class="notification-empty">
                    <i class="fa-regular fa-bell-slash" aria-hidden="true"></i>
                    <span>No recent notifications.</span>
                </div>
            `;
            return;
        }

        notificationList.innerHTML = notifications.map((notification) => {
            const id = Number(notification.id || 0);
            const rawType = notification.type === 'contact' ? 'contact_message' : (notification.type || 'order');
            const isContactMessage = rawType === 'contact_message';
            const type = escapeHtml(rawType);
            const contactName = notification.name || 'Guest';
            const contactTopic = notification.topic || notification.status || 'General support';
            const title = escapeHtml(notification.title || (isContactMessage ? `New message from ${contactName}` : `Order #${id}`));
            const subtitle = escapeHtml(notification.subtitle || (isContactMessage ? `Contact message: ${contactTopic}` : `Status: ${notification.status || 'Pending'}`));
            const date = escapeHtml(formatDate(notification.created_at || ''));
            const readClass = Number(notification.is_read || 0) === 1 ? ' is-read' : '';
            const href = escapeHtml(notification.href || (isContactMessage ? `admin-contact-messages.php?id=${id}` : `admin-order-details.php?id=${id}`));

            return `
                <a href="${href}" class="notification-item${readClass}" data-notification-type="${type}" data-notification-id="${id}" role="menuitem">
                    <span class="notification-dot" aria-hidden="true"></span>
                    <span class="notification-content">
                        <strong>${title}</strong>
                        <span>${subtitle}</span>
                        <time>${date}</time>
                    </span>
                </a>
            `;
        }).join('');
    };

    const loadNotifications = async () => {
        const response = await fetch('admin_notifications.php', {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('Unable to load notifications.');
        }

        const data = await response.json();
        renderNotifications(data);
    };

    try {
        await loadNotifications();
    } catch (error) {
        notificationButton.title = 'Notifications unavailable';
        notificationList.innerHTML = `
            <div class="notification-empty">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <span>Unable to load notifications.</span>
            </div>
        `;
    }

    notificationButton.addEventListener('click', async (event) => {
        event.stopPropagation();
        const isOpen = dropdown.hidden;
        dropdown.hidden = !isOpen;
        notificationMenu.classList.toggle('is-open', isOpen);
        notificationButton.setAttribute('aria-expanded', String(isOpen));

        if (isOpen) {
            try {
                await loadNotifications();
            } catch (error) {
                notificationList.innerHTML = `
                    <div class="notification-empty">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        <span>Unable to load notifications.</span>
                    </div>
                `;
            }
        }
    });

    notificationList.addEventListener('click', async (event) => {
        const notificationLink = event.target.closest('.notification-item');

        if (!notificationLink) {
            return;
        }

        event.preventDefault();
        const notificationType = notificationLink.dataset.notificationType || 'order';
        const notificationId = Number(notificationLink.dataset.notificationId || 0);
        const destination = notificationLink.href;

        try {
            const formData = new FormData();
            formData.append('action', 'mark_read');
            formData.append('type', notificationType);
            formData.append(notificationType === 'contact_message' ? 'message_id' : 'order_id', String(notificationId));

            await fetch('admin_notifications.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
            });
        } finally {
            window.location.href = destination;
        }
    });

    document.addEventListener('click', (event) => {
        if (!notificationMenu.contains(event.target)) {
            dropdown.hidden = true;
            notificationMenu.classList.remove('is-open');
            notificationButton.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            dropdown.hidden = true;
            notificationMenu.classList.remove('is-open');
            notificationButton.setAttribute('aria-expanded', 'false');
        }
    });
};

document.addEventListener('DOMContentLoaded', () => {
    const moreMenu = document.querySelector('.more-menu');
    const moreMenuButton = document.querySelector('.more-menu-button');
    const refreshButton = document.querySelector('[data-action="refresh"]');
    const darkModeButtons = document.querySelectorAll('[data-action="dark-mode"]');
    const profileButtons = document.querySelectorAll('#profileBtn, .profile-button');

    if (localStorage.getItem(darkModeStorageKey) === 'enabled') {
        document.body.classList.add('dark-mode');
    }

    profileButtons.forEach((profileButton) => {
        profileButton.addEventListener('click', () => {
            window.location.href = 'admin-profile.php';
        });
    });

    if (moreMenu && moreMenuButton) {
        moreMenuButton.addEventListener('click', (event) => {
            event.stopPropagation();
            const isOpen = moreMenu.classList.toggle('is-open');
            moreMenuButton.setAttribute('aria-expanded', String(isOpen));
        });

        document.addEventListener('click', (event) => {
            if (!moreMenu.contains(event.target)) {
                closeMoreMenu(moreMenu, moreMenuButton);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeMoreMenu(moreMenu, moreMenuButton);
            }
        });
    }

    if (refreshButton) {
        refreshButton.addEventListener('click', () => {
            window.location.reload();
        });
    }

    darkModeButtons.forEach((darkModeButton) => {
        darkModeButton.addEventListener('click', () => {
            const darkModeEnabled = document.body.classList.toggle('dark-mode');
            localStorage.setItem(darkModeStorageKey, darkModeEnabled ? 'enabled' : 'disabled');
            closeMoreMenu(moreMenu, moreMenuButton);
        });
    });

    setupSearch();
    setupNotificationCount();
});
