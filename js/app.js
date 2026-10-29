// ============================================
// BARBERBOOK - Main JavaScript
// ============================================

// --- TOAST NOTIFICATIONS ---
function showToast(message, type = 'info') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    const icons = { success: '✓', error: '✗', info: 'ℹ' };
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<span>${icons[type] || 'ℹ'}</span><span>${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

// --- MODAL MANAGEMENT ---
function openModal(id) {
    const overlay = document.getElementById(id);
    if (overlay) { overlay.classList.add('open'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
    const overlay = document.getElementById(id);
    if (overlay) { overlay.classList.remove('open'); document.body.style.overflow = ''; }
}
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
        document.body.style.overflow = '';
    }
    if (e.target.classList.contains('modal-close')) {
        const modal = e.target.closest('.modal-overlay');
        if (modal) { modal.classList.remove('open'); document.body.style.overflow = ''; }
    }
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(m => {
            m.classList.remove('open');
            document.body.style.overflow = '';
        });
    }
});

// --- CONFIRM DIALOGS ---
function confirmAction(message, callback) {
    if (confirm(message)) callback();
}

// --- FORM HELPERS ---
function formToJSON(form) {
    const data = {};
    new FormData(form).forEach((value, key) => { data[key] = value; });
    return data;
}

// --- GEOLOCATION ---
let userLocation = null;
function getUserLocation(callback) {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            pos => {
                userLocation = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                if (callback) callback(userLocation);
            },
            err => {
                console.warn('Geolocation denied or failed:', err.message);
                if (callback) callback(null);
            },
            { timeout: 8000 }
        );
    } else {
        if (callback) callback(null);
    }
}

// --- DISTANCE CALCULATION ---
function haversineDistance(lat1, lon1, lat2, lon2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI/180) * Math.cos(lat2 * Math.PI/180) *
              Math.sin(dLon/2) * Math.sin(dLon/2);
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

// --- TIME SLOT SELECTOR ---
function initTimeSlots() {
    document.querySelectorAll('.time-slot.available').forEach(slot => {
        slot.addEventListener('click', function() {
            if (this.classList.contains('booked')) return;
            document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
            this.classList.add('selected');
            const timeInput = document.getElementById('selected_time');
            const dateInput = document.getElementById('selected_date');
            if (timeInput) timeInput.value = this.dataset.time;
            if (dateInput) dateInput.value = this.dataset.date;
            const bookBtn = document.getElementById('confirm-book-btn');
            if (bookBtn) bookBtn.disabled = false;
        });
    });
}

// --- SEARCH TABS ---
function initSearchTabs() {
    document.querySelectorAll('.search-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.search-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            const placeholder = { shop: 'Search by shop name...', barber: 'Search by barber name...', id: 'Enter 8-digit barber ID...' };
            const input = document.getElementById('search-input');
            if (input) input.placeholder = placeholder[this.dataset.type] || 'Search...';
            const typeInput = document.getElementById('search-type');
            if (typeInput) typeInput.value = this.dataset.type;
        });
    });
}

// --- ASYNC FETCH HELPER ---
async function apiFetch(url, options = {}) {
    try {
        const res = await fetch(url, {
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...options.headers },
            ...options
        });
        return await res.json();
    } catch (e) {
        console.error('API Error:', e);
        return { error: 'Request failed' };
    }
}

// --- WEEK NAVIGATION ---
let currentWeekOffset = 0;
function navigateWeek(direction) {
    currentWeekOffset += direction;
    const barberid = document.getElementById('barber-id-hidden')?.value;
    if (barberid) loadWeekSchedule(barberid, currentWeekOffset);
}
async function loadWeekSchedule(barberId, offset = 0) {
    const container = document.getElementById('schedule-container');
    if (!container) return;
    container.innerHTML = '<div class="empty-state"><div class="empty-icon">⏳</div><p>Loading schedule...</p></div>';
    const res = await apiFetch(`api/get_schedule.php?barber_id=${barberId}&offset=${offset}`);
    if (res.html) container.innerHTML = res.html;
}

// --- DYNAMIC SEARCH ---
let searchTimeout;
function initLiveSearch() {
    const input = document.getElementById('search-input');
    if (!input) return;
    input.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const q = this.value.trim();
        const type = document.getElementById('search-type')?.value || 'shop';
        if (q.length < 2) {
            const resultsBox = document.getElementById('search-results');
            if (resultsBox) resultsBox.innerHTML = '';
            return;
        }
        searchTimeout = setTimeout(async () => {
            const res = await apiFetch(`api/search.php?q=${encodeURIComponent(q)}&type=${type}`);
            renderSearchResults(res);
        }, 400);
    });
}

function renderSearchResults(data) {
    const box = document.getElementById('search-results');
    if (!box) return;
    if (!data.results || data.results.length === 0) {
        box.innerHTML = '<div class="empty-state"><div class="empty-icon">🔍</div><h3>No results found</h3><p>Try a different search term</p></div>';
        return;
    }
    let html = '<div class="shop-grid mt-3">';
    data.results.forEach(shop => {
        html += `
        <div class="shop-card">
            <div class="shop-card-header">
                <div class="shop-logo">✂️</div>
                <div>
                    <div class="shop-name">${shop.name}</div>
                    <div class="shop-distance">${shop.distance ? shop.distance.toFixed(1) + ' km away' : shop.city}</div>
                </div>
            </div>
            <div class="shop-card-body">
                <div class="shop-info">📍 ${shop.address}</div>
                <div class="shop-info">📞 ${shop.phone || 'N/A'}</div>
                <div class="stars">${renderStars(shop.avg_rating || 0)}</div>
            </div>
            <div class="shop-card-footer">
                <a href="shop.php?id=${shop.id}" class="btn btn-primary btn-sm">View Shop</a>
                <a href="book.php?shop_id=${shop.id}" class="btn btn-gold btn-sm">Book Now</a>
            </div>
        </div>`;
    });
    html += '</div>';
    box.innerHTML = html;
}

function renderStars(rating) {
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        stars += i <= Math.round(rating) ? '★' : '<span class="empty">★</span>';
    }
    return stars + ` <small class="text-muted">(${rating ? parseFloat(rating).toFixed(1) : '0.0'})</small>`;
}

// --- PASSWORD TOGGLE ---
function initPasswordToggle() {
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', function() {
            const input = document.getElementById(this.dataset.target);
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
            this.textContent = input.type === 'password' ? '👁' : '🙈';
        });
    });
}

// --- INIT ---
document.addEventListener('DOMContentLoaded', function() {
    initTimeSlots();
    initSearchTabs();
    initLiveSearch();
    initPasswordToggle();

    // Auto-dismiss alerts
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.4s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 400);
        }, 5000);
    });

    // Confirm dangerous actions
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function(e) {
            if (!confirm(this.dataset.confirm)) e.preventDefault();
        });
    });
});
