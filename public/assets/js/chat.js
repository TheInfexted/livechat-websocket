// Chat JavaScript - public/assets/js/chat.js
// Additional chat functionality and enhancements

document.addEventListener('DOMContentLoaded', function() {
    console.log('Chat enhancements loaded');
    initializeChatEnhancements();
});

function initializeChatEnhancements() {
    // Initialize emoji picker
    initializeEmojiSupport();
    
    // Initialize keyboard shortcuts
    initializeKeyboardShortcuts();
    
    // Initialize message features
    initializeMessageFeatures();
    
    // Initialize responsive features
    initializeResponsiveFeatures();
    
    // Initialize sound notifications
    initializeSoundNotifications();
    
    // Initialize dark mode toggle
    initializeDarkMode();
    
    // Initialize file sharing
    initializeFileSharing();
    
    // Initialize message commands
    initializeMessageCommands();
}

// Emoji Support
function initializeEmojiSupport() {
    const messageInput = document.getElementById('messageInput');
    if (!messageInput) return;
    
    // Common emoji shortcuts
    const emojiShortcuts = {
        ':)': '😊',
        ':D': '😃',
        ':(': '😢',
        ':P': '😛',
        ';)': '😉',
        '<3': '❤️',
        ':thumbsup:': '👍',
        ':thumbsdown:': '👎',
        ':fire:': '🔥',
        ':heart:': '❤️',
        ':smile:': '😊',
        ':laugh:': '😂',
        ':cry:': '😢',
        ':angry:': '😠',
        ':party:': '🎉'
    };
    
    messageInput.addEventListener('input', function() {
        let value = this.value;
        let cursorPos = this.selectionStart;
        
        // Replace emoji shortcuts
        for (const [shortcut, emoji] of Object.entries(emojiShortcuts)) {
            if (value.includes(shortcut)) {
                const newValue = value.replace(shortcut, emoji);
                const diff = newValue.length - value.length;
                this.value = newValue;
                this.setSelectionRange(cursorPos + diff, cursorPos + diff);
                break;
            }
        }
    });
    
    // Add emoji picker button
    addEmojiPickerButton();
}

function addEmojiPickerButton() {
    const messageInput = document.getElementById('messageInput');
    const sendButton = document.getElementById('sendButton');
    
    if (!messageInput || !sendButton) return;
    
    const emojiButton = document.createElement('button');
    emojiButton.className = 'btn btn-outline-secondary';
    emojiButton.type = 'button';
    emojiButton.innerHTML = '😊';
    emojiButton.title = 'Add emoji';
    
    emojiButton.addEventListener('click', function() {
        showEmojiPicker();
    });
    
    // Insert emoji button before send button
    sendButton.parentNode.insertBefore(emojiButton, sendButton);
}

function showEmojiPicker() {
    const emojis = ['😊', '😃', '😂', '😢', '😠', '😍', '😘', '🤔', '👍', '👎', '❤️', '🔥', '🎉', '👋', '💪', '🙏', '✨', '⭐', '🚀', '💎'];
    
    // Create emoji picker modal
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.innerHTML = `
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Select Emoji</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex flex-wrap gap-2">
                        ${emojis.map(emoji => `
                            <button class="btn btn-outline-light emoji-btn" data-emoji="${emoji}">${emoji}</button>
                        `).join('')}
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Handle emoji selection
    modal.addEventListener('click', function(e) {
        if (e.target.classList.contains('emoji-btn')) {
            const emoji = e.target.dataset.emoji;
            insertEmojiIntoMessage(emoji);
            bootstrap.Modal.getInstance(modal).hide();
        }
    });
    
    // Show modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    // Remove modal after hiding
    modal.addEventListener('hidden.bs.modal', function() {
        modal.remove();
    });
}

function insertEmojiIntoMessage(emoji) {
    const messageInput = document.getElementById('messageInput');
    if (!messageInput) return;
    
    const cursorPos = messageInput.selectionStart;
    const textBefore = messageInput.value.substring(0, cursorPos);
    const textAfter = messageInput.value.substring(cursorPos);
    
    messageInput.value = textBefore + emoji + textAfter;
    messageInput.setSelectionRange(cursorPos + emoji.length, cursorPos + emoji.length);
    messageInput.focus();
}

// Keyboard Shortcuts
function initializeKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + Enter to send message
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            const messageForm = document.getElementById('messageForm');
            if (messageForm) {
                messageForm.dispatchEvent(new Event('submit'));
            }
        }
        
        // Escape to clear message input
        if (e.key === 'Escape') {
            const messageInput = document.getElementById('messageInput');
            if (messageInput && document.activeElement === messageInput) {
                messageInput.value = '';
                messageInput.blur();
            }
        }
        
        // Alt + 1-9 to switch rooms
        if (e.altKey && /^[1-9]$/.test(e.key)) {
            e.preventDefault();
            const roomIndex = parseInt(e.key) - 1;
            const roomItems = document.querySelectorAll('.room-item');
            if (roomItems[roomIndex]) {
                roomItems[roomIndex].click();
            }
        }
        
        // Ctrl/Cmd + / to focus message input
        if ((e.ctrlKey || e.metaKey) && e.key === '/') {
            e.preventDefault();
            const messageInput = document.getElementById('messageInput');
            if (messageInput) {
                messageInput.focus();
            }
        }
    });
}

// Message Features
function initializeMessageFeatures() {
    // Auto-scroll to bottom when new messages arrive
    observeNewMessages();
    
    // Message timestamps on hover
    initializeMessageTimestamps();
    
    // Message editing (placeholder)
    initializeMessageEditing();
    
    // Message reactions (placeholder)
    initializeMessageReactions();
}

function observeNewMessages() {
    const messagesContainer = document.getElementById('messagesContainer');
    if (!messagesContainer) return;
    
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                // Check if user is near bottom before auto-scrolling
                const isNearBottom = messagesContainer.scrollTop + messagesContainer.clientHeight >= messagesContainer.scrollHeight - 100;
                
                if (isNearBottom) {
                    setTimeout(() => {
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    }, 100);
                }
            }
        });
    });
    
    observer.observe(messagesContainer, {
        childList: true,
        subtree: true
    });
}

function initializeMessageTimestamps() {
    // Show full timestamp on hover
    document.addEventListener('mouseover', function(e) {
        if (e.target.classList.contains('message-meta')) {
            const timestamp = e.target.textContent;
            e.target.title = `Sent at ${timestamp}`;
        }
    });
}

function initializeMessageEditing() {
    // Double-click to edit own messages (placeholder)
    document.addEventListener('dblclick', function(e) {
        const messageDiv = e.target.closest('.message.own');
        if (messageDiv) {
            console.log('Message editing not implemented yet');
            // Implement message editing functionality here
        }
    });
}

function initializeMessageReactions() {
    // Right-click for message reactions (placeholder)
    document.addEventListener('contextmenu', function(e) {
        const messageDiv = e.target.closest('.message');
        if (messageDiv && !messageDiv.classList.contains('system')) {
            e.preventDefault();
            console.log('Message reactions not implemented yet');
            // Implement message reactions functionality here
        }
    });
}

// Responsive Features
function initializeResponsiveFeatures() {
    // Mobile sidebar toggle
    addMobileSidebarToggle();
    
    // Responsive message layout
    adjustMessageLayoutForMobile();
    
    // Touch gestures
    initializeTouchGestures();
}

function addMobileSidebarToggle() {
    if (window.innerWidth <= 768) {
        const chatArea = document.querySelector('.col-md-9');
        const sidebar = document.querySelector('.sidebar');
        
        if (chatArea && sidebar) {
            const toggleButton = document.createElement('button');
            toggleButton.className = 'btn btn-primary d-md-none position-fixed';
            toggleButton.style.cssText = 'top: 10px; left: 10px; z-index: 1001;';
            toggleButton.innerHTML = '<i class="fas fa-bars"></i>';
            toggleButton.title = 'Toggle Sidebar';
            
            toggleButton.addEventListener('click', function() {
                sidebar.classList.toggle('show');
            });
            
            document.body.appendChild(toggleButton);
            
            // Close sidebar when clicking outside
            document.addEventListener('click', function(e) {
                if (!sidebar.contains(e.target) && !toggleButton.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            });
        }
    }
}

function adjustMessageLayoutForMobile() {
    // Adjust message bubble sizes for mobile
    if (window.innerWidth <= 576) {
        const style = document.createElement('style');
        style.textContent = `
            .message-bubble {
                max-width: 85% !important;
                font-size: 0.9rem !important;
            }
            .avatar {
                width: 28px !important;
                height: 28px !important;
                font-size: 0.8rem !important;
            }
        `;
        document.head.appendChild(style);
    }
}

function initializeTouchGestures() {
    let touchStartX = 0;
    let touchStartY = 0;
    
    document.addEventListener('touchstart', function(e) {
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
    });
    
    document.addEventListener('touchend', function(e) {
        const touchEndX = e.changedTouches[0].clientX;
        const touchEndY = e.changedTouches[0].clientY;
        const deltaX = touchEndX - touchStartX;
        const deltaY = touchEndY - touchStartY;
        
        // Swipe right to open sidebar on mobile
        if (Math.abs(deltaX) > Math.abs(deltaY) && deltaX > 50 && touchStartX < 50) {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar && window.innerWidth <= 768) {
                sidebar.classList.add('show');
            }
        }
        
        // Swipe left to close sidebar on mobile
        if (Math.abs(deltaX) > Math.abs(deltaY) && deltaX < -50) {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        }
    });
}

// Sound Notifications
function initializeSoundNotifications() {
    // Create audio context for notification sounds
    let audioContext;
    let soundEnabled = localStorage.getItem('chatSoundEnabled') !== 'false';
    
    // Add sound toggle button
    addSoundToggleButton();
    
    function addSoundToggleButton() {
        const connectionStatus = document.getElementById('connectionStatus');
        if (!connectionStatus) return;
        
        const soundButton = document.createElement('button');
        soundButton.className = 'btn btn-sm btn-outline-secondary ms-2';
        soundButton.innerHTML = soundEnabled ? '<i class="fas fa-volume-up"></i>' : '<i class="fas fa-volume-mute"></i>';
        soundButton.title = soundEnabled ? 'Mute notifications' : 'Enable sound notifications';
        
        soundButton.addEventListener('click', function() {
            soundEnabled = !soundEnabled;
            localStorage.setItem('chatSoundEnabled', soundEnabled);
            
            soundButton.innerHTML = soundEnabled ? '<i class="fas fa-volume-up"></i>' : '<i class="fas fa-volume-mute"></i>';
            soundButton.title = soundEnabled ? 'Mute notifications' : 'Enable sound notifications';
        });
        
        connectionStatus.parentNode.appendChild(soundButton);
    }
    
    // Play notification sound
    window.playChatNotificationSound = function() {
        if (!soundEnabled) return;
        
        try {
            // Create a simple beep sound
            if (!audioContext) {
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
            }
            
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
            oscillator.frequency.setValueAtTime(400, audioContext.currentTime + 0.1);
            
            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 0.2);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.2);
        } catch (error) {
            console.warn('Could not play notification sound:', error);
        }
    };
}

// Dark Mode Toggle
function initializeDarkMode() {
    // Check for saved dark mode preference
    const isDarkMode = localStorage.getItem('chatDarkMode') === 'true';
    
    // Apply dark mode if saved
    if (isDarkMode) {
        document.body.classList.add('dark-mode');
    }
    
    // Add dark mode toggle button
    addDarkModeToggle();
}

function addDarkModeToggle() {
    const connectionStatus = document.getElementById('connectionStatus');
    if (!connectionStatus) return;
    
    const darkModeButton = document.createElement('button');
    darkModeButton.className = 'btn btn-sm btn-outline-secondary ms-2';
    darkModeButton.innerHTML = document.body.classList.contains('dark-mode') ? 
        '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
    darkModeButton.title = 'Toggle dark mode';
    
    darkModeButton.addEventListener('click', function() {
        const isDarkMode = document.body.classList.toggle('dark-mode');
        localStorage.setItem('chatDarkMode', isDarkMode);
        
        darkModeButton.innerHTML = isDarkMode ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
    });
    
    connectionStatus.parentNode.appendChild(darkModeButton);
}

// File Sharing (Basic Implementation)
function initializeFileSharing() {
    const messageInput = document.getElementById('messageInput');
    if (!messageInput) return;
    
    // Add file upload button
    const fileButton = document.createElement('button');
    fileButton.className = 'btn btn-outline-secondary';
    fileButton.type = 'button';
    fileButton.innerHTML = '<i class="fas fa-paperclip"></i>';
    fileButton.title = 'Attach file';
    
    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.style.display = 'none';
    fileInput.accept = 'image/*,.pdf,.doc,.docx,.txt';
    
    fileButton.addEventListener('click', function() {
        fileInput.click();
    });
    
    fileInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            handleFileUpload(file);
        }
    });
    
    // Insert file button before emoji button
    const inputGroup = messageInput.parentNode;
    const firstButton = inputGroup.querySelector('button');
    if (firstButton) {
        inputGroup.insertBefore(fileButton, firstButton);
        inputGroup.appendChild(fileInput);
    }
}

function handleFileUpload(file) {
    // Basic file validation
    const maxSize = 5 * 1024 * 1024; // 5MB
    if (file.size > maxSize) {
        alert('File size must be less than 5MB');
        return;
    }
    
    // For now, just show file name in message input
    const messageInput = document.getElementById('messageInput');
    if (messageInput) {
        messageInput.value = `📎 ${file.name}`;
        messageInput.focus();
    }
    
    // TODO: Implement actual file upload to server
    console.log('File upload not fully implemented yet:', file.name);
}

// Message Commands
function initializeMessageCommands() {
    const messageInput = document.getElementById('messageInput');
    if (!messageInput) return;
    
    messageInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            const message = this.value.trim();
            
            // Handle special commands
            if (message.startsWith('/')) {
                e.preventDefault();
                handleCommand(message);
                this.value = '';
                return;
            }
        }
    });
}

function handleCommand(command) {
    const [cmd, ...args] = command.slice(1).split(' ');
    
    switch (cmd.toLowerCase()) {
        case 'help':
            showHelpMessage();
            break;
        case 'clear':
            clearMessages();
            break;
        case 'status':
            if (args[0]) {
                updateStatus(args[0]);
            }
            break;
        default:
            showSystemMessage(`Unknown command: /${cmd}. Type /help for available commands.`);
    }
}

function showHelpMessage() {
    const helpText = `
Available commands:
/help - Show this help message
/clear - Clear message history
/status [online|away|offline] - Change your status
    `;
    showSystemMessage(helpText);
}

function clearMessages() {
    const messagesContainer = document.getElementById('messagesContainer');
    if (messagesContainer) {
        messagesContainer.innerHTML = `
            <div class="text-center text-muted py-3">
                <i class="fas fa-broom"></i> Messages cleared
            </div>
        `;
    }
}

// Initialize message commands
document.addEventListener('DOMContentLoaded', function() {
    initializeMessageCommands();
});

// Export functions for use in main chat script
window.ChatEnhancements = {
    playChatNotificationSound: window.playChatNotificationSound,
    insertEmojiIntoMessage,
    handleFileUpload,
    showHelpMessage,
    clearMessages
};