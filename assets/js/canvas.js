document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('pixelCanvas');
    const ctx = canvas.getContext('2d');
    const colorPicker = document.getElementById('colorPicker');
    const cooldownInfo = document.getElementById('cooldownInfo');
    const cooldownTimer = document.getElementById('cooldownTimer');
    
    let isDrawing = false;
    let cooldownActive = false;
    let cooldownInterval;

    // Initialize canvas
    ctx.fillStyle = '#FFFFFF';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    // Load initial canvas state
    loadCanvasState();

    // Handle pixel placement
    canvas.addEventListener('click', function(e) {
        if (cooldownActive) {
            return;
        }

        const rect = canvas.getBoundingClientRect();
        const x = Math.floor((e.clientX - rect.left) * (canvas.width / canvas.offsetWidth));
        const y = Math.floor((e.clientY - rect.top) * (canvas.height / canvas.offsetHeight));
        
        placePixel(x, y, colorPicker.value);
    });

    // Load canvas state from server
    function loadCanvasState() {
        fetch('/api/canvas.php')
            .then(response => response.json())
            .then(data => {
                data.pixels.forEach(pixel => {
                    ctx.fillStyle = pixel.color;
                    ctx.fillRect(pixel.x, pixel.y, 1, 1);
                });
            })
            .catch(error => console.error('Error loading canvas:', error));
    }

    // Place pixel on canvas and send to server
    function placePixel(x, y, color) {
        fetch('/api/canvas.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ x, y, color })
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                if (data.remainingTime) {
                    startCooldown(data.remainingTime);
                }
                throw new Error(data.error);
            }

            // Update canvas
            ctx.fillStyle = color;
            ctx.fillRect(x, y, 1, 1);

            // Start cooldown
            startCooldown(data.nextCooldown);
        })
        .catch(error => {
            console.error('Error placing pixel:', error);
            alert(error.message || 'Failed to place pixel');
        });
    }

    // Handle cooldown timer
    function startCooldown(duration) {
        cooldownActive = true;
        cooldownInfo.classList.remove('hidden');
        
        let timeLeft = duration;
        updateCooldownDisplay(timeLeft);

        clearInterval(cooldownInterval);
        cooldownInterval = setInterval(() => {
            timeLeft -= 0.1;
            updateCooldownDisplay(timeLeft);

            if (timeLeft <= 0) {
                clearInterval(cooldownInterval);
                cooldownActive = false;
                cooldownInfo.classList.add('hidden');
            }
        }, 100);
    }

    function updateCooldownDisplay(timeLeft) {
        cooldownTimer.textContent = timeLeft.toFixed(1) + 's';
    }

    // Handle canvas zoom and pan
    let scale = 1;
    let panning = false;
    let startPoint = { x: 0, y: 0 };
    let endPoint = { x: 0, y: 0 };

    canvas.addEventListener('wheel', function(e) {
        e.preventDefault();
        const delta = e.deltaY;
        const scaleAmount = delta > 0 ? 0.9 : 1.1;
        
        // Limit zoom level
        const newScale = scale * scaleAmount;
        if (newScale >= 0.5 && newScale <= 10) {
            scale = newScale;
            
            // Get mouse position relative to canvas
            const rect = canvas.getBoundingClientRect();
            const mouseX = e.clientX - rect.left;
            const mouseY = e.clientY - rect.top;
            
            // Apply transform
            ctx.save();
            ctx.translate(mouseX, mouseY);
            ctx.scale(scaleAmount, scaleAmount);
            ctx.translate(-mouseX, -mouseY);
            ctx.restore();
        }
    });

    canvas.addEventListener('mousedown', function(e) {
        panning = true;
        startPoint = { x: e.clientX - endPoint.x, y: e.clientY - endPoint.y };
    });

    canvas.addEventListener('mousemove', function(e) {
        if (!panning) return;
        
        endPoint = { x: e.clientX - startPoint.x, y: e.clientY - startPoint.y };
        
        ctx.save();
        ctx.translate(endPoint.x, endPoint.y);
        ctx.restore();
    });

    canvas.addEventListener('mouseup', function() {
        panning = false;
    });

    canvas.addEventListener('mouseleave', function() {
        panning = false;
    });

    // WebSocket connection for real-time updates
    const ws = new WebSocket(`ws://${window.location.hostname}:8080`);
    
    ws.onmessage = function(event) {
        const data = JSON.parse(event.data);
        if (data.type === 'pixel') {
            ctx.fillStyle = data.color;
            ctx.fillRect(data.x, data.y, 1, 1);
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
