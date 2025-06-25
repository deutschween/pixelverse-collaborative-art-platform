document.addEventListener('DOMContentLoaded', function() {
    const casesList = document.getElementById('casesList');
    const userCurrency = document.getElementById('userCurrency');
    
    // Load initial cases
    loadCases();

    // Update currency display
    updateCurrency();

    function loadCases() {
        fetch('/api/cases.php')
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }

                casesList.innerHTML = '';
                if (data.cases.length === 0) {
                    casesList.innerHTML = `
                        <p class="text-gray-500 text-center py-4">No cases available</p>
                    `;
                    return;
                }

                data.cases.forEach(case_ => {
                    const caseElement = createCaseElement(case_);
                    casesList.appendChild(caseElement);
                });
            })
            .catch(error => {
                console.error('Error loading cases:', error);
                casesList.innerHTML = `
                    <p class="text-red-500 text-center py-4">Failed to load cases</p>
                `;
            });
    }

    function createCaseElement(case_) {
        const div = document.createElement('div');
        div.className = 'flex items-center justify-between p-2 bg-gray-50 rounded-lg';

        const info = document.createElement('div');
        info.className = 'flex items-center gap-2';

        const icon = document.createElement('i');
        icon.className = 'fas fa-gift text-purple-500';
        info.appendChild(icon);

        const name = document.createElement('span');
        name.textContent = `${case_.name}`;
        info.appendChild(name);

        const rarity = document.createElement('span');
        rarity.className = 'text-xs text-gray-500';
        rarity.textContent = '⭐'.repeat(case_.rarity);
        info.appendChild(rarity);

        const openButton = document.createElement('button');
        openButton.className = 'px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500';
        openButton.textContent = 'Open';
        openButton.onclick = () => openCase(case_.id);

        div.appendChild(info);
        div.appendChild(openButton);

        return div;
    }

    function openCase(caseId) {
        fetch('/api/cases.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ caseId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }

            // Show reward animation
            showRewardAnimation(data.reward);

            // Update cases list
            loadCases();

            // Update currency if reward was currency
            if (data.reward.type === 'currency') {
                updateCurrency();
            }
        })
        .catch(error => {
            console.error('Error opening case:', error);
            alert(error.message || 'Failed to open case');
        });
    }

    function showRewardAnimation(reward) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        
        const content = document.createElement('div');
        content.className = 'bg-white rounded-lg p-8 text-center transform scale-0 transition-transform duration-300';
        
        const icon = document.createElement('i');
        if (reward.type === 'badge') {
            icon.className = `fas ${reward.badge.icon} text-6xl text-yellow-500 mb-4`;
        } else {
            icon.className = 'fas fa-coins text-6xl text-yellow-500 mb-4';
        }
        content.appendChild(icon);

        const title = document.createElement('h3');
        title.className = 'text-2xl font-bold mb-2';
        title.textContent = reward.type === 'badge' ? reward.badge.name : 'Currency Reward!';
        content.appendChild(title);

        const description = document.createElement('p');
        description.className = 'text-gray-600';
        description.textContent = reward.type === 'badge' 
            ? reward.badge.description 
            : `You received ${reward.amount} coins!`;
        content.appendChild(description);

        modal.appendChild(content);
        document.body.appendChild(modal);

        // Trigger animation
        setTimeout(() => {
            content.classList.add('scale-100');
        }, 100);

        // Remove modal after animation
        setTimeout(() => {
            content.classList.remove('scale-100');
            setTimeout(() => {
                document.body.removeChild(modal);
            }, 300);
        }, 3000);
    }

    function updateCurrency() {
        fetch('/api/user.php')
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                userCurrency.textContent = data.currency;
            })
            .catch(error => console.error('Error updating currency:', error));
    }
});
