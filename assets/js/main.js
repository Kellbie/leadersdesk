// Main JavaScript for LeaderDesk - COMPLETE FIXED VERSION

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Main.js loaded - Initializing...');
    
    // DOM Elements
    const menuToggle = document.getElementById('menuToggle');
    const sideNav = document.getElementById('sideNav');
    const notificationsBtn = document.getElementById('notificationsBtn');
    const notificationsDropdown = document.getElementById('notificationsDropdown');
    
    console.log('📋 Elements found:', {
        menuToggle: !!menuToggle,
        sideNav: !!sideNav,
        notificationsBtn: !!notificationsBtn,
        notificationsDropdown: !!notificationsDropdown
    });

    // ==================== MOBILE MENU TOGGLE ====================
    if (menuToggle && sideNav) {
        menuToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('📱 Menu toggle clicked');
            sideNav.classList.toggle('open');
            menuToggle.textContent = sideNav.classList.contains('open') ? '✕' : '☰';
        });
    }

    // ==================== NOTIFICATIONS DROPDOWN ====================
    if (notificationsBtn && notificationsDropdown) {
        // Toggle dropdown on click
        notificationsBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('🔔 Notifications clicked');
            notificationsDropdown.classList.toggle('show');
            
            // Load notifications when opened
            if (notificationsDropdown.classList.contains('show')) {
                loadNotifications();
            }
        });

        // Prevent clicks inside dropdown from closing it
        notificationsDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }

    // ==================== CLOSE DROPDOWNS WHEN CLICKING OUTSIDE ====================
    document.addEventListener('click', function(e) {
        // Close notifications dropdown
        if (notificationsDropdown && notificationsBtn && !notificationsBtn.contains(e.target)) {
            notificationsDropdown.classList.remove('show');
        }
        
        // Close mobile menu when clicking outside
        if (window.innerWidth < 768 && sideNav && menuToggle && 
            !sideNav.contains(e.target) && !menuToggle.contains(e.target)) {
            sideNav.classList.remove('open');
            if (menuToggle) menuToggle.textContent = '☰';
        }
    });

    // ==================== NOTIFICATION FUNCTIONS ====================
    async function loadNotifications() {
        console.log('📥 Loading notifications...');
        try {
            const response = await fetch('ajax/get_notifications.php');
            const data = await response.json();
            console.log('📨 Notifications data:', data);
            
            if (data.success && data.notifications) {
                renderNotifications(data.notifications);
                updateNotificationBadge(data.unread_count);
            } else if (data.notifications) {
                renderNotifications(data.notifications);
                updateNotificationBadge(data.unread_count || 0);
            }
        } catch (error) {
            console.error('❌ Error loading notifications:', error);
        }
    }

    function renderNotifications(notifications) {
        const container = document.getElementById('notificationsList');
        if (!container) {
            console.warn('⚠️ Notifications container not found');
            return;
        }

        if (!notifications || notifications.length === 0) {
            container.innerHTML = `
                <div class="no-notifications">
                    <span>📬</span>
                    <p>No notifications yet</p>
                </div>
            `;
            return;
        }

        let html = '';
        notifications.slice(0, 5).forEach(notification => {
            const icon = getNotificationIcon(notification.type);
            const timeAgo = timeAgo(notification.created_at);
            const unreadClass = notification.is_read == 0 ? 'unread' : '';
            
            html += `
                <div class="notification-item ${unreadClass}" 
                     data-id="${notification.id}"
                     onclick="markNotificationRead(${notification.id})">
                    <div class="notification-icon">${icon}</div>
                    <div class="notification-content">
                        <div class="notification-title">${escapeHtml(notification.title)}</div>
                        <div class="notification-message">${escapeHtml(notification.message)}</div>
                        <div class="notification-time">${timeAgo}</div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    function getNotificationIcon(type) {
        const icons = {
            'task': '✅',
            'member': '👤',
            'event': '📅',
            'training': '📚',
            'target': '🎯',
            'system': '🔔'
        };
        return icons[type] || '🔔';
    }

    function updateNotificationBadge(count) {
        const badge = document.getElementById('notificationCount');
        if (badge) {
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        }
    }

    function timeAgo(timestamp) {
        const now = new Date();
        const past = new Date(timestamp);
        const diff = Math.floor((now - past) / 1000);

        if (diff < 60) return 'just now';
        if (diff < 3600) {
            const minutes = Math.floor(diff / 60);
            return minutes + ' minute' + (minutes > 1 ? 's' : '') + ' ago';
        }
        if (diff < 86400) {
            const hours = Math.floor(diff / 3600);
            return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
        }
        const days = Math.floor(diff / 86400);
        return days + ' day' + (days > 1 ? 's' : '') + ' ago';
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Make functions globally available
    window.markNotificationRead = async function(id) {
        console.log('📌 Marking notification as read:', id);
        try {
            await fetch('ajax/mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + id
            });
            loadNotifications();
        } catch (error) {
            console.error('❌ Error marking notification as read:', error);
        }
    };

    window.markAllAsRead = async function() {
        console.log('📌 Marking all as read');
        try {
            await fetch('ajax/mark_all_notifications_read.php', {
                method: 'POST'
            });
            loadNotifications();
            showToast('All notifications marked as read', 'success');
        } catch (error) {
            console.error('❌ Error marking all as read:', error);
        }
    };

    // ==================== TOAST NOTIFICATION SYSTEM ====================
    function showToast(message, type = 'info', duration = 3000) {
        const container = document.querySelector('.toast-container');
        if (!container) {
            // Create container if it doesn't exist
            const newContainer = document.createElement('div');
            newContainer.className = 'toast-container';
            document.body.appendChild(newContainer);
        }
        
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        let icon = 'ℹ️';
        switch(type) {
            case 'success': icon = '✅'; break;
            case 'error': icon = '❌'; break;
            case 'warning': icon = '⚠️'; break;
        }
        
        toast.innerHTML = `
            <span class="toast-icon">${icon}</span>
            <span class="toast-message">${message}</span>
        `;
        
        document.querySelector('.toast-container').appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, duration);
    }

    window.showToast = showToast;

    // ==================== INITIAL LOADS ====================
    // Load notifications immediately
    loadNotifications();
    
    // Refresh notifications every 30 seconds
    setInterval(loadNotifications, 30000);

    // ==================== ANIMATIONS ====================
    // Animate stat cards on scroll
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.stat-card').forEach(card => {
        observer.observe(card);
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.transition = 'opacity 0.3s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        });
    }, 5000);
});

// ==================== MODAL SYSTEM ====================
class ModalManager {
    constructor() {
        this.modals = new Map();
        this.init();
    }

    init() {
        document.querySelectorAll('[data-modal]').forEach(trigger => {
            const modalId = trigger.dataset.modal;
            const modal = document.getElementById(modalId);
            
            if (modal) {
                this.modals.set(modalId, modal);
                
                trigger.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.open(modalId);
                });
                
                const closeBtn = modal.querySelector('.modal-close');
                if (closeBtn) {
                    closeBtn.addEventListener('click', () => this.close(modalId));
                }
                
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        this.close(modalId);
                    }
                });
            }
        });
    }

    open(modalId) {
        const modal = this.modals.get(modalId);
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }

    close(modalId) {
        const modal = this.modals.get(modalId);
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }
}

// Initialize modal manager
const modalManager = new ModalManager();
window.modalManager = modalManager;
window.openModal = (modalId) => modalManager.open(modalId);
window.closeModal = (modalId) => modalManager.close(modalId);

// ==================== FORM VALIDATION ====================
class FormValidator {
    constructor(form) {
        this.form = form;
        this.errors = new Map();
        this.init();
    }

    init() {
        this.form.addEventListener('submit', (e) => {
            if (!this.validate()) {
                e.preventDefault();
            }
        });

        this.form.querySelectorAll('[data-validate]').forEach(field => {
            field.addEventListener('blur', () => {
                this.validateField(field);
            });
        });
    }

    validate() {
        this.errors.clear();
        const fields = this.form.querySelectorAll('[data-validate]');
        
        fields.forEach(field => {
            this.validateField(field);
        });

        if (this.errors.size === 0) {
            return true;
        } else {
            this.showErrors();
            return false;
        }
    }

    validateField(field) {
        const rules = field.dataset.validate.split(' ');
        const value = field.value.trim();
        
        for (const rule of rules) {
            switch(rule) {
                case 'required':
                    if (!value) {
                        this.setError(field, 'This field is required');
                        return;
                    }
                    break;
                    
                case 'email':
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (value && !emailRegex.test(value)) {
                        this.setError(field, 'Please enter a valid email');
                        return;
                    }
                    break;
                    
                case 'phone':
                    const phoneRegex = /^[\d\s\+\-\(\)]{10,}$/;
                    if (value && !phoneRegex.test(value)) {
                        this.setError(field, 'Please enter a valid phone number');
                        return;
                    }
                    break;
                    
                case 'password':
                    if (value && value.length < 6) {
                        this.setError(field, 'Password must be at least 6 characters');
                        return;
                    }
                    break;
            }
        }
        
        this.clearError(field);
    }

    setError(field, message) {
        this.errors.set(field, message);
        field.classList.add('error');
        
        const errorDiv = field.nextElementSibling;
        if (errorDiv && errorDiv.classList.contains('error-message')) {
            errorDiv.textContent = message;
        }
    }

    clearError(field) {
        this.errors.delete(field);
        field.classList.remove('error');
        
        const errorDiv = field.nextElementSibling;
        if (errorDiv && errorDiv.classList.contains('error-message')) {
            errorDiv.textContent = '';
        }
    }

    showErrors() {
        this.errors.forEach((message) => {
            if (window.showToast) {
                window.showToast(message, 'error');
            } else {
                alert(message);
            }
        });
    }
}

// Initialize form validators
document.querySelectorAll('form[data-validate]').forEach(form => {
    new FormValidator(form);
});

// ==================== AJAX REQUEST HELPER ====================
async function ajaxRequest(url, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };

    if (data && (method === 'POST' || method === 'PUT')) {
        options.body = JSON.stringify(data);
    }

    try {
        const response = await fetch(url, options);
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || 'Request failed');
        }
        
        return result;
    } catch (error) {
        console.error('❌ AJAX Error:', error);
        if (window.showToast) {
            window.showToast(error.message, 'error');
        }
        throw error;
    }
}

window.ajaxRequest = ajaxRequest;

// ==================== PIPELINE DRAG AND DROP ====================
class PipelineDragDrop {
    constructor() {
        this.draggedCard = null;
        this.stages = document.querySelectorAll('.pipeline-stage');
        this.init();
    }

    init() {
        document.querySelectorAll('.pipeline-card').forEach(card => {
            card.setAttribute('draggable', 'true');
            
            card.addEventListener('dragstart', (e) => {
                this.draggedCard = card;
                card.classList.add('dragging');
                e.dataTransfer.setData('text/plain', card.dataset.id);
            });
            
            card.addEventListener('dragend', () => {
                card.classList.remove('dragging');
                this.draggedCard = null;
            });
        });

        this.stages.forEach(stage => {
            stage.addEventListener('dragover', (e) => {
                e.preventDefault();
                stage.classList.add('drag-over');
            });
            
            stage.addEventListener('dragleave', () => {
                stage.classList.remove('drag-over');
            });
            
            stage.addEventListener('drop', (e) => {
                e.preventDefault();
                stage.classList.remove('drag-over');
                
                const prospectId = e.dataTransfer.getData('text/plain');
                const newStage = stage.dataset.stage;
                const stageContent = stage.querySelector('.stage-content');
                
                if (this.draggedCard && newStage && this.draggedCard.dataset.stage !== newStage) {
                    // Update via AJAX
                    fetch('ajax/update_prospect_stage.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'prospect_id=' + prospectId + '&stage=' + newStage
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            stageContent.appendChild(this.draggedCard);
                            this.draggedCard.dataset.stage = newStage;
                            this.updateStageCounts();
                            if (window.showToast) {
                                window.showToast('Prospect stage updated', 'success');
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error updating prospect stage:', error);
                        if (window.showToast) {
                            window.showToast('Failed to update prospect stage', 'error');
                        }
                    });
                }
            });
        });
    }

    updateStageCounts() {
        this.stages.forEach(stage => {
            const cards = stage.querySelectorAll('.pipeline-card').length;
            const countBadge = stage.querySelector('.stage-count');
            if (countBadge) {
                countBadge.textContent = cards;
            }
        });
    }
}

// Initialize pipeline if it exists
if (document.querySelector('.pipeline-container')) {
    new PipelineDragDrop();
}

// ==================== TEAM TREE VIEW ====================
class TeamTree {
    constructor(container) {
        this.container = container;
        this.expandedNodes = new Set();
        this.init();
    }

    init() {
        this.container.querySelectorAll('.expand-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const node = btn.closest('.tree-node');
                this.toggleNode(node);
            });
        });

        this.container.querySelectorAll('.node-content').forEach(node => {
            node.addEventListener('click', () => {
                const memberId = node.dataset.memberId;
                if (memberId) {
                    this.loadMemberProfile(memberId);
                }
            });
        });
    }

    toggleNode(node) {
        const children = node.querySelector('.tree-children');
        if (children) {
            if (children.style.display === 'none' || !children.style.display) {
                children.style.display = 'block';
                node.querySelector('.expand-btn').textContent = '−';
            } else {
                children.style.display = 'none';
                node.querySelector('.expand-btn').textContent = '+';
            }
        }
    }

    async loadMemberProfile(memberId) {
        try {
            const data = await ajaxRequest(`ajax/get_member_profile.php?id=${memberId}`);
            this.showMemberModal(data);
        } catch (error) {
            console.error('Error loading member profile:', error);
        }
    }

    showMemberModal(member) {
        const modal = document.getElementById('memberModal');
        if (modal) {
            modal.querySelector('.member-name').textContent = member.name;
            modal.querySelector('.member-rank').textContent = member.rank;
            modal.querySelector('.member-stats').innerHTML = `
                <div>Downline: ${member.downline_count}</div>
                <div>Activity Score: ${member.activity_score}</div>
                <div>Recruits: ${member.total_recruits}</div>
                <div>Sales: $${member.total_sales}</div>
            `;
            
            if (window.modalManager) {
                window.modalManager.open('memberModal');
            }
        }
    }
}

// Initialize team tree if it exists
if (document.getElementById('teamTree')) {
    new TeamTree(document.getElementById('teamTree'));
}

// ==================== ACTIVITY FEED ====================
class ActivityFeed {
    constructor(container) {
        this.container = container;
        this.page = 1;
        this.loading = false;
        this.hasMore = true;
        this.init();
    }

    init() {
        this.loadActivities();
        
        window.addEventListener('scroll', () => {
            if (this.shouldLoadMore()) {
                this.loadMore();
            }
        });
    }

    async loadActivities() {
        if (this.loading || !this.hasMore) return;
        
        this.loading = true;
        this.showLoader();
        
        try {
            const data = await ajaxRequest(`ajax/get_activities.php?page=${this.page}`);
            
            if (data.activities && data.activities.length > 0) {
                this.renderActivities(data.activities);
                this.page++;
                this.hasMore = data.has_more;
            } else {
                this.hasMore = false;
            }
        } catch (error) {
            console.error('Error loading activities:', error);
        } finally {
            this.loading = false;
            this.hideLoader();
        }
    }

    loadMore() {
        if (this.shouldLoadMore()) {
            this.loadActivities();
        }
    }

    shouldLoadMore() {
        if (!this.hasMore || this.loading) return false;
        
        const scrollPosition = window.innerHeight + window.scrollY;
        const threshold = document.documentElement.scrollHeight - 1000;
        
        return scrollPosition >= threshold;
    }

    renderActivities(activities) {
        activities.forEach(activity => {
            const activityEl = this.createActivityElement(activity);
            this.container.appendChild(activityEl);
        });
    }

    createActivityElement(activity) {
        const div = document.createElement('div');
        div.className = 'list-item fade-in';
        div.innerHTML = `
            <div class="item-avatar">${activity.user_name ? activity.user_name.charAt(0) : '?'}</div>
            <div class="item-content">
                <div class="item-title">${this.escapeHtml(activity.action)}</div>
                <div class="item-subtitle">${this.escapeHtml(activity.description)}</div>
                <div class="item-meta">${activity.time_ago || 'just now'} • ${activity.points_earned} points</div>
            </div>
        `;
        return div;
    }

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    showLoader() {
        const loader = document.createElement('div');
        loader.className = 'text-center p-3';
        loader.id = 'activity-loader';
        loader.innerHTML = '<div class="spinner"></div>';
        this.container.appendChild(loader);
    }

    hideLoader() {
        const loader = document.getElementById('activity-loader');
        if (loader) {
            loader.remove();
        }
    }
}

// Initialize activity feed if it exists
if (document.getElementById('activityFeed')) {
    new ActivityFeed(document.getElementById('activityFeed'));
}

// ==================== CHARTS AND GRAPHS ====================
class ChartManager {
    constructor() {
        this.charts = new Map();
    }

    createBarChart(canvasId, data) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        const chart = {
            draw: () => {
                const maxValue = Math.max(...data.values);
                const barWidth = (canvas.width - 40) / data.labels.length - 10;
                
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                
                data.values.forEach((value, index) => {
                    const x = 20 + index * (barWidth + 10);
                    const barHeight = (value / maxValue) * (canvas.height - 60);
                    const y = canvas.height - 30 - barHeight;
                    
                    ctx.fillStyle = '#1a1a1a';
                    ctx.fillRect(x, y, barWidth, barHeight);
                    
                    ctx.fillStyle = '#333';
                    ctx.font = '12px Inter';
                    ctx.textAlign = 'center';
                    ctx.fillText(value, x + barWidth/2, y - 5);
                    ctx.fillText(data.labels[index], x + barWidth/2, canvas.height - 10);
                });
            }
        };
        
        chart.draw();
        this.charts.set(canvasId, chart);
        
        window.addEventListener('resize', () => {
            canvas.width = canvas.offsetWidth;
            canvas.height = canvas.offsetHeight;
            chart.draw();
        });
    }
}

// Initialize chart manager
const chartManager = new ChartManager();
window.chartManager = chartManager;

// ==================== SEARCH AND FILTER ====================
class SearchFilter {
    constructor(inputId, targetSelector) {
        this.input = document.getElementById(inputId);
        this.targets = document.querySelectorAll(targetSelector);
        this.init();
    }

    init() {
        if (this.input) {
            this.input.addEventListener('input', () => {
                this.filter();
            });
        }
    }

    filter() {
        const searchTerm = this.input.value.toLowerCase();
        
        this.targets.forEach(target => {
            const text = target.textContent.toLowerCase();
            const match = text.includes(searchTerm);
            target.style.display = match ? '' : 'none';
        });
    }
}

// Initialize search filters
document.querySelectorAll('[data-search]').forEach(input => {
    new SearchFilter(input.id, input.dataset.target);
});

// ==================== EXPORT FUNCTIONALITY ====================
class ExportManager {
    static async exportData(type, format = 'csv') {
        try {
            const data = await ajaxRequest(`ajax/export.php?type=${type}&format=${format}`);
            
            if (format === 'csv') {
                this.downloadCSV(data, `${type}_export.csv`);
            } else if (format === 'pdf') {
                window.open(data.url, '_blank');
            }
            
            if (window.showToast) {
                window.showToast('Export completed successfully', 'success');
            }
        } catch (error) {
            if (window.showToast) {
                window.showToast('Export failed', 'error');
            }
        }
    }

    static downloadCSV(data, filename) {
        const blob = new Blob([data], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        window.URL.revokeObjectURL(url);
    }
}

window.exportData = ExportManager.exportData;