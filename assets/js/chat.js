document.addEventListener('DOMContentLoaded', function() {
    const chatMessages = document.getElementById('chatMessages');
    const chatInput = document.getElementById('chatInput');
    const sendMessage = document.getElementById('sendMessage');
    
    let lastMessageId = 0;
    let isLoadingMessages = false;

    // Load initial messages
    loadMessages();

    // Send message
    if (sendMessage && chatInput) {
        sendMessage.addEventListener('click', function() {
            sendChatMessage();
        });

        chatInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendChatMessage();
            }
        });
    }

    // Auto-scroll chat on new messages
    if (chatMessages) {
        chatMessages.addEventListener('scroll', function() {
            // Load more messages when scrolling to top
            if (chatMessages.scrollTop === 0 && !isLoadingMessages) {
                loadMessages(true);
            }
        });
    }

    function sendChatMessage() {
        const message = chatInput.value.trim();
        if (!message) return;

        fetch('/api/chat.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ message })
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            
            chatInput.value = '';
            appendMessage(data.message);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        })
        .catch(error => {
            console.error('Error sending message:', error);
            alert(error.message || 'Failed to send message');
        });
    }

    function loadMessages(loadMore = false) {
        if (isLoadingMessages) return;
        isLoadingMessages = true;

        const params = new URLSearchParams();
        if (loadMore && lastMessageId) {
            params.append('before', lastMessageId);
        }

        fetch('/api/chat.php?' + params.toString())
            .then(response => response.json())
            .then(data => {
                if (data.messages.length > 0) {
                    const scrollHeight = chatMessages.scrollHeight;
                    
                    if (loadMore) {
                        data.messages.forEach(message => prependMessage(message));
                    } else {
                        data.messages.forEach(message => appendMessage(message));
                        chatMessages.scrollTop = chatMessages.scrollHeight;
                    }

                    if (loadMore) {
                        // Maintain scroll position when loading more messages
                        chatMessages.scrollTop = chatMessages.scrollHeight - scrollHeight;
                    }

                    lastMessageId = data.messages[0].id;
                }
            })
            .catch(error => console.error('Error loading messages:', error))
            .finally(() => {
                isLoadingMessages = false;
            });
    }

    function appendMessage(message) {
        const messageElement = createMessageElement(message);
        chatMessages.appendChild(messageElement);
    }

    function prependMessage(message) {
        const messageElement = createMessageElement(message);
        chatMessages.insertBefore(messageElement, chatMessages.firstChild);
    }

    function createMessageElement(message) {
        const div = document.createElement('div');
        div.className = 'p-2 hover:bg-gray-50 rounded-lg';

        const header = document.createElement('div');
        header.className = 'flex items-center gap-2 mb-1';

        const username = document.createElement('span');
        username.className = 'font-medium';
        username.textContent = message.username;
        header.appendChild(username);

        // Add badges if any
        if (message.badges && message.badges.length > 0) {
            message.badges.forEach(badge => {
                const badgeIcon = document.createElement('i');
                badgeIcon.className = `fas ${badge} text-yellow-500`;
                header.appendChild(badgeIcon);
            });
        }

        const timestamp = document.createElement('span');
        timestamp.className = 'text-xs text-gray-500';
        timestamp.textContent = new Date(message.createdAt).toLocaleTimeString();
        header.appendChild(timestamp);

        const content = document.createElement('div');
        content.className = 'text-gray-700';
        content.textContent = message.message;

        div.appendChild(header);
        div.appendChild(content);

        return div;
    }

    // WebSocket connection for real-time chat updates
    const ws = new WebSocket(`ws://${window.location.hostname}:8080`);
    
    ws.onmessage = function(event) {
        const data = JSON.parse(event.data);
        if (data.type === 'chat') {
            appendMessage(data.message);
            if (chatMessages.scrollTop + chatMessages.clientHeight >= chatMessages.scrollHeight - 100) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }
    };

    ws.onerror = function(error) {
        console.error('WebSocket error:', error);
    };

    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        ws.close();
    });
});
