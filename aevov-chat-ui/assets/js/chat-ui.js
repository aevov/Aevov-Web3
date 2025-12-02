jQuery(document).ready(function($) {
    const messagesContainer = $('#aevov-chat-messages');
    const input = $('#aevov-chat-input');
    const sendButton = $('#aevov-chat-send');

    sendButton.on('click', function() {
        const message = input.val();
        if (message.trim() === '') {
            return;
        }

        appendMessage(message, 'user');
        input.val('');

        // Send message to the backend
        $.ajax({
            url: aevovChatUISettings.ajax_url,
            type: 'POST',
            data: {
                action: 'aevov_chat_send_message',
                nonce: aevovChatUISettings.nonce,
                message: message,
            },
            success: function(response) {
                if (response.success) {
                    let reply = response.data.reply;
                    if (response.data.data) {
                        reply += '\n<pre>' + JSON.stringify(response.data.data, null, 2) + '</pre>';
                    }
                    appendMessage(reply, 'bot');
                } else {
                    appendMessage('Error: ' + response.data.message, 'bot');
                }
            },
            error: function() {
                appendMessage('An error occurred while sending the message.', 'bot');
            }
        });
    });

    input.on('keypress', function(e) {
        if (e.which === 13) {
            sendButton.click();
        }
    });

    function appendMessage(message, sender) {
        const messageElement = $('<div class="aevov-chat-message ' + sender + '">');
        const contentElement = $('<div class="message-content">').html(message);
        messageElement.append(contentElement);
        messagesContainer.append(messageElement);
        messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
    }
});
