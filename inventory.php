<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Require login
requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - PixelVerse</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm p-4">
        <div class="container mx-auto flex justify-between items-center">
            <a href="/" class="text-2xl font-bold">PixelVerse</a>
            <div class="flex items-center gap-4">
                <span class="text-gray-600">
                    <i class="fas fa-coins mr-2"></i>
                    <span id="userCurrency">0</span>
                </span>
                <a href="inventory.php" class="text-blue-600">
                    <i class="fas fa-box-open mr-2"></i>
                    Inventory
                </a>
                <a href="auth/logout.php" class="text-red-600">
                    <i class="fas fa-sign-out-alt mr-2"></i>
                    Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mx-auto mt-8">
        <!-- Active Boosts -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-8">
            <h2 class="text-xl font-bold mb-4">
                <i class="fas fa-bolt mr-2"></i>
                Active Boosts
            </h2>
            <div id="activeBoosts" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Active boosts will be loaded here -->
            </div>
        </div>

        <!-- Inventory Items -->
        <div class="bg-white rounded-lg shadow-sm p-4">
            <h2 class="text-xl font-bold mb-4">
                <i class="fas fa-box-open mr-2"></i>
                Inventory
            </h2>
            <div id="inventoryItems" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Inventory items will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Use Item Modal -->
    <div id="useItemModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg p-6 max-w-md w-full">
            <h3 class="text-xl font-bold mb-4">Use Item</h3>
            <p id="useItemDescription" class="text-gray-600 mb-4"></p>
            <div class="flex justify-end gap-4">
                <button onclick="closeUseItemModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                    Cancel
                </button>
                <button id="confirmUseItem" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Use
                </button>
            </div>
        </div>
    </div>

    <script>
        let selectedItemId = null;

        // Load inventory and boosts
        function loadInventory() {
            fetch('/api/inventory.php')
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }

                    // Update active boosts
                    const activeBoostsContainer = document.getElementById('activeBoosts');
                    activeBoostsContainer.innerHTML = '';

                    if (data.activeBoosts.length === 0) {
                        activeBoostsContainer.innerHTML = `
                            <p class="text-gray-500 col-span-full text-center py-4">
                                No active boosts
                            </p>
                        `;
                    } else {
                        data.activeBoosts.forEach(boost => {
                            const expiresIn = Math.max(0, Math.floor((new Date(boost.expires_at) - new Date()) / 1000));
                            const minutes = Math.floor(expiresIn / 60);
                            const seconds = expiresIn % 60;

                            const div = document.createElement('div');
                            div.className = 'p-4 bg-blue-50 rounded-lg';
                            div.innerHTML = `
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="font-medium">${formatBoostType(boost.boost_type)}</h3>
                                        <p class="text-sm text-gray-500">
                                            Expires in ${minutes}m ${seconds}s
                                        </p>
                                    </div>
                                    <i class="fas fa-bolt text-yellow-500 text-xl"></i>
                                </div>
                            `;
                            activeBoostsContainer.appendChild(div);
                        });
                    }

                    // Update inventory items
                    const inventoryContainer = document.getElementById('inventoryItems');
                    inventoryContainer.innerHTML = '';

                    if (data.inventory.length === 0) {
                        inventoryContainer.innerHTML = `
                            <p class="text-gray-500 col-span-full text-center py-4">
                                No items in inventory
                            </p>
                        `;
                    } else {
                        data.inventory.forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'inventory-item p-4 bg-gray-50 rounded-lg';
                            div.innerHTML = `
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="font-medium">${item.name}</h3>
                                        <p class="text-sm text-gray-500">${item.description}</p>
                                    </div>
                                    <button 
                                        onclick="showUseItemModal(${item.id}, '${item.name}', '${item.description}')"
                                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                                    >
                                        Use
                                    </button>
                                </div>
                            `;
                            inventoryContainer.appendChild(div);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error loading inventory:', error);
                });
        }

        // Format boost type for display
        function formatBoostType(type) {
            return type.split('_')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ');
        }

        // Show use item modal
        function showUseItemModal(itemId, name, description) {
            selectedItemId = itemId;
            document.getElementById('useItemDescription').textContent = 
                `Are you sure you want to use ${name}? ${description}`;
            document.getElementById('useItemModal').classList.remove('hidden');
        }

        // Close use item modal
        function closeUseItemModal() {
            selectedItemId = null;
            document.getElementById('useItemModal').classList.add('hidden');
        }

        // Handle use item
        document.getElementById('confirmUseItem').addEventListener('click', function() {
            if (!selectedItemId) return;

            fetch('/api/inventory.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ inventoryItemId: selectedItemId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                closeUseItemModal();
                loadInventory();
            })
            .catch(error => {
                console.error('Error using item:', error);
                alert(error.message || 'Failed to use item');
            });
        });

        // Update currency display
        function updateCurrency() {
            fetch('/api/user.php')
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    document.getElementById('userCurrency').textContent = data.currency;
                })
                .catch(error => console.error('Error updating currency:', error));
        }

        // Initial load
        loadInventory();
        updateCurrency();

        // Refresh active boosts every second
        setInterval(loadInventory, 1000);
    </script>
</body>
</html>
