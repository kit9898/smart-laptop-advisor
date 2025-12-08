    </main>
    <footer>
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> Smart Laptop Advisor by Chan Shao Heng. All Rights Reserved.</p>
        </div>
    </footer>

<!-- ===== ASUS-STYLE CHATBOT HTML (Enhanced with Product Cards & Persistence) ===== -->
<div id="chat-widget" class="chat-widget">
    <div class="chat-header">
        <div class="chat-header-brand">
            <div class="chat-avatar">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                </svg>
            </div>
            <div class="chat-header-info">
                <h4>AI Assistant</h4>
                <span class="chat-status"><span class="status-dot"></span>Online</span>
            </div>
        </div>
        <div class="chat-header-controls">
            <button id="new-chat" class="chat-header-btn" title="Start New Chat">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
            </button>
            <button id="maximize-chat" class="chat-header-btn" title="Maximize">
                <svg id="maximize-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>
                <svg id="restore-icon" style="display: none;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M5 16h3v3h2v-5H5v2zm3-8H5v2h5V5H8v3zm6 11h2v-3h3v-2h-5v5zm2-11V5h-2v5h5V8h-3z"/></svg>
            </button>
            <button id="minimize-chat" class="chat-header-btn" title="Minimize">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M6 19h12v2H6z"/></svg>
            </button>
            <button id="end-chat" class="chat-header-btn" title="Close">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </button>
        </div>
    </div>
    <!-- Rating Banner (like ASUS) -->
    <div id="chat-rating-banner" class="chat-rating-banner" style="display: none;">
        <span class="rating-chevron">»</span>
        <span>Rate our AI Assistant Here</span>
        <a href="#" id="rate-go-btn">GO</a>
    </div>
    <div id="chat-body" class="chat-body">
        <div id="typing-indicator" class="typing-indicator">
            <span></span><span></span><span></span>
        </div>
    </div>
    <!-- Quick Suggestion Buttons -->
    <div id="chat-suggestions" class="chat-suggestions"></div>
    <div class="chat-footer">
        <form id="chat-form" class="chat-form">
            <input type="text" id="chat-input" placeholder="Message AI Assistant..." autocomplete="off">
            <button type="submit" aria-label="Send" id="chat-send-btn">
                <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"></path></svg>
            </button>
        </form>
    </div>
</div>

<button id="chat-toggle" class="chat-toggle">
    <svg xmlns="http://www.w3.org/2000/svg" height="28" viewBox="0 0 24 24" width="28" fill="white"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg>
    <span class="chat-toggle-label">Chat with us</span>
</button>
<!-- ===== CHATBOT HTML END ===== -->

<!-- Markdown Parser for Chat Messages -->
<script src="https://cdn.jsdelivr.net/npm/marked@9.1.6/marked.min.js"></script>

<script>
// --- Mobile Navigation Script ---
const hamburger = document.querySelector('.hamburger');
const navLinks = document.querySelector('.nav-links');
if(hamburger) {
    hamburger.addEventListener('click', () => {
        navLinks.classList.toggle('nav-active');
        hamburger.classList.toggle('toggle');
    });
}

// ===== ASUS-STYLE AI CHATBOT WITH PERSISTENT HISTORY =====
const chatWidget = document.getElementById('chat-widget');
const chatToggle = document.getElementById('chat-toggle');
const maximizeChat = document.getElementById('maximize-chat');
const maximizeIcon = document.getElementById('maximize-icon');
const restoreIcon = document.getElementById('restore-icon');
const minimizeChat = document.getElementById('minimize-chat');
const endChat = document.getElementById('end-chat');
const newChatBtn = document.getElementById('new-chat');
const chatForm = document.getElementById('chat-form');
const chatInput = document.getElementById('chat-input');
const chatBody = document.getElementById('chat-body');
const typingIndicator = document.getElementById('typing-indicator');
const chatSuggestions = document.getElementById('chat-suggestions');
const ratingBanner = document.getElementById('chat-rating-banner');

let sessionId = null;
let historyLoaded = false;
let isMinimized = false;
let isSending = false;
let productLinks = {}; // Store product name -> link mapping
let chatCache = []; // Cache all chat data including products

// Initialize session ID from localStorage
const storedSessionId = localStorage.getItem('chat_session_id');
if (storedSessionId) {
    sessionId = storedSessionId;
    // Load cached chat data
    try {
        const cached = localStorage.getItem('chat_cache_' + sessionId);
        if (cached) {
            chatCache = JSON.parse(cached);
        }
    } catch (e) {
        console.error('Error loading chat cache:', e);
    }
}

// Default quick suggestions (ASUS-style)
const defaultSuggestions = [
    { text: 'Product Specifications', query: 'What are the specifications of the top laptops?' },
    { text: 'Product Recommendation', query: 'Can you recommend the latest laptop for students?' },
    { text: 'Compare Products', query: 'What are the differences between gaming and business laptops?' },
    { text: 'Accessory Suggestion', query: 'Can you recommend a wireless mouse?' }
];

// ===== CHAT WINDOW CONTROLS =====
function openChat() {
    chatWidget.classList.add('open');
    chatWidget.classList.remove('minimized', 'maximized');
    toggleMaximizeIcon(false);
    chatToggle.style.display = 'none';
    isMinimized = false;
    
    // Load chat history if not loaded
    if (!historyLoaded) {
        initializeChat();
    }
    chatInput.focus();
}

function minimize() {
    chatWidget.classList.remove('open');
    chatWidget.classList.add('minimized');
    chatWidget.classList.remove('maximized');
    toggleMaximizeIcon(false);
    chatToggle.style.display = 'flex';
    isMinimized = true;
}

function closeChat() {
    chatWidget.classList.remove('open', 'minimized', 'maximized');
    toggleMaximizeIcon(false);
    chatToggle.style.display = 'flex';
    isMinimized = false;
}

function startNewChat() {
    // Clear old cache
    if (sessionId) {
        localStorage.removeItem('chat_cache_' + sessionId);
    }
    sessionId = null;
    localStorage.removeItem('chat_session_id');
    historyLoaded = false;
    productLinks = {};
    chatCache = [];
    clearChatBody();
    initializeChat();
}

function toggleMaximize() {
    chatWidget.classList.toggle('maximized');
    const isMaximized = chatWidget.classList.contains('maximized');
    toggleMaximizeIcon(isMaximized);
    if (isMaximized) {
        chatToggle.style.display = 'none';
    } else if (!chatWidget.classList.contains('open')) {
        chatToggle.style.display = 'flex';
    }
}

function toggleMaximizeIcon(isMaximized) {
    maximizeIcon.style.display = isMaximized ? 'none' : 'inline-block';
    restoreIcon.style.display = isMaximized ? 'inline-block' : 'none';
    maximizeChat.title = isMaximized ? 'Restore Down' : 'Maximize';
}

// Event Listeners
chatToggle.addEventListener('click', () => {
    if (isMinimized) {
        openChat();
    } else {
        chatWidget.classList.remove('maximized');
        toggleMaximizeIcon(false);
        chatWidget.classList.toggle('open');
        if (chatWidget.classList.contains('open')) {
            chatToggle.style.display = 'none';
            if (!historyLoaded) initializeChat();
            chatInput.focus();
        } else {
            chatToggle.style.display = 'flex';
        }
    }
});

maximizeChat.addEventListener('click', toggleMaximize);
minimizeChat.addEventListener('click', minimize);
endChat.addEventListener('click', closeChat);
newChatBtn.addEventListener('click', startNewChat);

// ===== MESSAGE SENDING =====
chatForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const userInput = chatInput.value.trim();
    if (userInput === '' || isSending) return;
    await sendMessage(userInput);
});

async function sendMessage(userInput) {
    if (!sessionId) {
        displayMessage('Initializing chat session...', 'system');
        await startNewSession();
        if (!sessionId) {
            displayMessage('Failed to start chat session. Please try again.', 'system');
            return;
        }
    }
    
    displayMessage(userInput, 'user');
    chatInput.value = '';
    
    // Cache user message
    chatCache.push({
        type: 'user',
        message: userInput,
        timestamp: new Date().toISOString()
    });
    saveChatCache();
    
    showTypingIndicator();
    hideSuggestions();
    isSending = true;
    
    // Get currency info
    const currency = localStorage.getItem('selected_currency') || 'MYR';
    let rate = 4.47;
    try {
        const storedRates = JSON.parse(localStorage.getItem('currency_rates'));
        if (storedRates && storedRates.rates && storedRates.rates[currency]) {
            rate = storedRates.rates[currency];
        }
    } catch (e) {
        console.error('Error parsing currency rates', e);
    }

    try {
        const response = await fetch('chatbot_process.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'send_message',
                session_id: sessionId,
                message: userInput,
                currency: currency,
                exchange_rate: rate,
                current_page: window.location.pathname
            })
        });
        
        const data = await response.json();
        hideTypingIndicator();
        
        if (data.success) {
            // Store product links for text highlighting
            if (data.product_links) {
                productLinks = {...productLinks, ...data.product_links};
            }
            
            // Cache this response
            chatCache.push({
                type: 'bot',
                response: data.response,
                products: data.products || [],
                also_like: data.also_like || [],
                actions: data.actions || [],
                product_links: data.product_links || {},
                timestamp: new Date().toISOString()
            });
            saveChatCache();
            
            // Display text response with clickable product names
            displayMessage(data.response, 'bot', data.product_links);
            
            // Display product cards
            if (data.products && data.products.length > 0) {
                displayProductCards(data.products);
            }
            
            // Display "You may also like" section
            if (data.also_like && data.also_like.length > 0) {
                displayAlsoLike(data.also_like);
            }
            
            // Display action buttons
            if (data.actions && data.actions.length > 0) {
                displayActionButtons(data.actions);
            }
            
            // Display "Was this helpful?" feedback
            displayFeedbackPrompt();
            
            // Update suggestions
            showSuggestions(data.suggestions || defaultSuggestions);
            
            // Show rating banner after some messages
            ratingBanner.style.display = 'flex';
        } else {
            displayMessage('Error: ' + data.error, 'system');
            showSuggestions(defaultSuggestions);
        }
    } catch (error) {
        hideTypingIndicator();
        displayMessage('Sorry, I\'m having trouble connecting right now. Please check that Ollama is running.', 'bot');
        console.error('Error:', error);
        showSuggestions(defaultSuggestions);
    } finally {
        isSending = false;
    }
}

// ===== CHAT INITIALIZATION WITH HISTORY PERSISTENCE =====
async function initializeChat() {
    // If we have a session and cached data, restore from cache
    if (sessionId && chatCache.length > 0) {
        displayWelcomeMessage();
        restoreFromCache();
        showSuggestions(defaultSuggestions);
        ratingBanner.style.display = 'flex';
        historyLoaded = true;
        return;
    }
    
    // If we have a session but no cache, try to load from server
    if (sessionId) {
        const loaded = await loadChatHistory();
        if (loaded) {
            showSuggestions(defaultSuggestions);
            ratingBanner.style.display = 'flex';
            return;
        }
    }
    
    // No history - start fresh
    await startNewSession();
    
    if (sessionId) {
        displayWelcomeMessage();
        showSuggestions(defaultSuggestions);
        historyLoaded = true;
    } else {
        displayMessage('Failed to initialize chat. Please try again.', 'system');
    }
}

// Restore chat from cache (includes product cards)
function restoreFromCache() {
    chatCache.forEach(item => {
        if (item.type === 'user') {
            displayMessage(item.message, 'user');
        } else if (item.type === 'bot') {
            // Restore product links
            if (item.product_links) {
                productLinks = {...productLinks, ...item.product_links};
            }
            
            // Display text response
            displayMessage(item.response, 'bot', item.product_links);
            
            // Display product cards
            if (item.products && item.products.length > 0) {
                displayProductCards(item.products);
            }
            
            // Display "You may also like"
            if (item.also_like && item.also_like.length > 0) {
                displayAlsoLike(item.also_like);
            }
            
            // Display action buttons
            if (item.actions && item.actions.length > 0) {
                displayActionButtons(item.actions);
            }
        }
    });
    
    chatBody.scrollTop = chatBody.scrollHeight;
}

// Save chat cache to localStorage
function saveChatCache() {
    if (sessionId) {
        try {
            localStorage.setItem('chat_cache_' + sessionId, JSON.stringify(chatCache));
        } catch (e) {
            console.error('Error saving chat cache:', e);
        }
    }
}

async function loadChatHistory() {
    try {
        const response = await fetch('chatbot_process.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'get_history',
                session_id: sessionId
            })
        });
        
        const data = await response.json();
        
        if (data.success && data.history && data.history.length > 0) {
            // Display welcome message first
            displayWelcomeMessage();
            
            // Replay history
            data.history.forEach(msg => {
                displayMessage(msg.message, msg.sender === 'user' ? 'user' : 'bot');
            });
            
            historyLoaded = true;
            chatBody.scrollTop = chatBody.scrollHeight;
            return true;
        }
        return false;
    } catch (error) {
        console.error('Error loading chat history:', error);
        return false;
    }
}

async function startNewSession() {
    try {
        const response = await fetch('/fyp/LaptopAdvisor/chatbot_process.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'start_session' })
        });
        
        const data = await response.json();
        
        if (data.success) {
            sessionId = data.session_id;
            localStorage.setItem('chat_session_id', sessionId);
            return true;
        }
        return false;
    } catch (error) {
        console.error('Error starting session:', error);
        return false;
    }
}

function displayWelcomeMessage() {
    const welcomeHTML = `
        <div class="chat-welcome">
            <div class="welcome-avatar">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                </svg>
            </div>
            <div class="welcome-content">
                <p class="welcome-greeting">Hello! I'm the <strong>Smart Laptop Advisor</strong>.</p>
                <p>Ask me for help navigating our products, finding the information you need, or getting personalized recommendations. How can I assist you today?</p>
            </div>
        </div>
    `;
    chatBody.insertBefore(createElementFromHTML(welcomeHTML), typingIndicator);
}

// ===== MESSAGE DISPLAY WITH CLICKABLE PRODUCT NAMES =====
function displayMessage(message, sender, productLinksMap = null) {
    const messageElement = document.createElement('div');
    messageElement.classList.add('chat-message', sender);
    
    if (sender === 'bot' && typeof marked !== 'undefined') {
        marked.setOptions({
            breaks: true,
            gfm: true,
            headerIds: false,
            mangle: false
        });
        
        let renderedHTML = marked.parse(message);
        
        // Make product names clickable
        if (productLinksMap) {
            for (const [name, link] of Object.entries(productLinksMap)) {
                const regex = new RegExp(`\\*\\*${escapeRegex(name)}\\*\\*|${escapeRegex(name)}`, 'gi');
                renderedHTML = renderedHTML.replace(regex, 
                    `<a href="${link}" class="product-link">${name}</a>`);
            }
        }
        
        messageElement.innerHTML = renderedHTML;
    } else {
        messageElement.innerHTML = message.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    }
    
    chatBody.insertBefore(messageElement, typingIndicator);
    chatBody.scrollTop = chatBody.scrollHeight;
}

// Escape special regex characters
function escapeRegex(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

// ===== PRODUCT CARDS (ASUS-style with Buy button) =====
function displayProductCards(products) {
    const cardsContainer = document.createElement('div');
    cardsContainer.classList.add('chat-product-cards');
    
    products.forEach(product => {
        const card = document.createElement('div');
        card.classList.add('chat-product-card');
        
        // Build specs display
        const specs = [];
        if (product.specs.ram) specs.push(product.specs.ram);
        if (product.specs.storage) specs.push(product.specs.storage);
        
        card.innerHTML = `
            <div class="product-card-image">
                <img src="${product.image}" alt="${product.name}" onerror="this.src='images/laptop1.png'">
            </div>
            <div class="product-card-info">
                <span class="product-card-brand">${product.brand}</span>
                <h5 class="product-card-name">${product.name}</h5>
                ${specs.length > 0 ? `<div class="product-card-specs">${specs.map(s => `<span>${s}</span>`).join('')}</div>` : ''}
                <div class="product-card-price-row">
                    <span class="price-label">eStore price starting at</span>
                    <span class="product-card-price">${product.price}</span>
                </div>
                <div class="product-card-actions">
                    <a href="${product.buy_link || product.link}" class="btn-card-buy">Buy</a>
                </div>
            </div>
        `;
        
        // Store link for text highlighting
        productLinks[product.name] = product.link;
        
        // Make card clickable
        card.addEventListener('click', (e) => {
            if (!e.target.closest('a')) {
                window.location.href = product.link;
            }
        });
        
        cardsContainer.appendChild(card);
    });
    
    chatBody.insertBefore(cardsContainer, typingIndicator);
    chatBody.scrollTop = chatBody.scrollHeight;
}

// ===== "YOU MAY ALSO LIKE" SECTION =====
function displayAlsoLike(products) {
    if (products.length === 0) return;
    
    const container = document.createElement('div');
    container.classList.add('chat-also-like');
    
    container.innerHTML = `
        <div class="also-like-header">You may also like</div>
        <div class="also-like-products">
            ${products.map(p => `
                <a href="${p.link}" class="also-like-item">
                    <img src="${p.image}" alt="${p.name}" onerror="this.src='images/laptop1.png'">
                    <span class="also-like-name">${p.name}</span>
                </a>
            `).join('')}
        </div>
    `;
    
    chatBody.insertBefore(container, typingIndicator);
    chatBody.scrollTop = chatBody.scrollHeight;
}

// ===== ACTION BUTTONS =====
function displayActionButtons(actions) {
    const actionsContainer = document.createElement('div');
    actionsContainer.classList.add('chat-action-buttons');
    
    actions.forEach(action => {
        const btn = document.createElement('a');
        btn.href = action.url;
        btn.classList.add('chat-action-btn');
        btn.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">${getActionIcon(action.type)}</svg>
            ${action.label}
        `;
        actionsContainer.appendChild(btn);
    });
    
    chatBody.insertBefore(actionsContainer, typingIndicator);
    chatBody.scrollTop = chatBody.scrollHeight;
}

function getActionIcon(type) {
    const icons = {
        'compare': '<path d="M10 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h5v-2H5V5h5V3zm4 18h5c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-5v2h5v14h-5v2zm-1-6l-4-4 1.41-1.41L12 11.17V4h2v7.17l1.59-1.59L17 11l-4 4z"/>',
        'browse': '<path d="M4 8h4V4H4v4zm6 12h4v-4h-4v4zm-6 0h4v-4H4v4zm0-6h4v-4H4v4zm6 0h4v-4h-4v4zm6-10v4h4V4h-4zm-6 4h4V4h-4v4zm6 6h4v-4h-4v4zm0 6h4v-4h-4v4z"/>',
        'cart': '<path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/>'
    };
    return icons[type] || icons['browse'];
}

// ===== FEEDBACK PROMPT (Was this helpful?) =====
function displayFeedbackPrompt() {
    const feedbackContainer = document.createElement('div');
    feedbackContainer.classList.add('chat-feedback');
    feedbackContainer.innerHTML = `
        <span class="feedback-text">Was this helpful?</span>
        <button class="feedback-btn" data-feedback="yes" title="Yes">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M1 21h4V9H1v12zm22-11c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L14.17 1 7.59 7.59C7.22 7.95 7 8.45 7 9v10c0 1.1.9 2 2 2h9c.83 0 1.54-.5 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73v-2z"/></svg>
        </button>
        <button class="feedback-btn" data-feedback="no" title="No">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M15 3H6c-.83 0-1.54.5-1.84 1.22l-3.02 7.05c-.09.23-.14.47-.14.73v2c0 1.1.9 2 2 2h6.31l-.95 4.57-.03.32c0 .41.17.79.44 1.06L9.83 23l6.59-6.59c.36-.36.58-.86.58-1.41V5c0-1.1-.9-2-2-2zm4 0v12h4V3h-4z"/></svg>
        </button>
    `;
    
    // Add feedback handlers
    feedbackContainer.querySelectorAll('.feedback-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const feedback = this.getAttribute('data-feedback');
            feedbackContainer.innerHTML = `<span class="feedback-thanks">Thanks for your feedback!</span>`;
            // Could send to server here
        });
    });
    
    chatBody.insertBefore(feedbackContainer, typingIndicator);
    chatBody.scrollTop = chatBody.scrollHeight;
}

// ===== SUGGESTIONS =====
function showSuggestions(suggestions) {
    chatSuggestions.innerHTML = '';
    suggestions.forEach(suggestion => {
        const btn = document.createElement('button');
        btn.classList.add('suggestion-btn');
        btn.textContent = suggestion.text;
        btn.addEventListener('click', () => sendMessage(suggestion.query));
        chatSuggestions.appendChild(btn);
    });
    chatSuggestions.style.display = 'flex';
}

function hideSuggestions() {
    chatSuggestions.style.display = 'none';
}

// ===== UTILITY FUNCTIONS =====
function clearChatBody() {
    const elements = chatBody.querySelectorAll('.chat-message, .chat-welcome, .chat-product-cards, .chat-action-buttons, .chat-also-like, .chat-feedback');
    elements.forEach(el => el.remove());
}

function showTypingIndicator() {
    typingIndicator.style.display = 'flex';
    chatBody.scrollTop = chatBody.scrollHeight;
}

function hideTypingIndicator() {
    typingIndicator.style.display = 'none';
}

function createElementFromHTML(htmlString) {
    const div = document.createElement('div');
    div.innerHTML = htmlString.trim();
    return div.firstChild;
}

// ===== AUTO-RESTORE CHAT STATE ON PAGE LOAD =====
// If chat was open before navigation, restore it
if (localStorage.getItem('chat_was_open') === 'true') {
    setTimeout(() => openChat(), 500);
}

// Save chat state before leaving
window.addEventListener('beforeunload', () => {
    localStorage.setItem('chat_was_open', chatWidget.classList.contains('open') ? 'true' : 'false');
});
</script>
</body>
</html>
<?php
if(isset($conn)){
    $conn->close();
}
?>
