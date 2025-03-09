class DynamicMargin {
    constructor() {
        this.init();
    }

    init() {
        console.log('DynamicMargin initialized');
        this.startPriceCheck();
        this.initEventListeners();
    }

    initEventListeners() {
        if (typeof prestashop !== 'undefined') {
            prestashop.on('updateCart', () => {
                console.log('Cart updated, checking prices...');
                this.checkPrices();
            });
        }
    }

    startPriceCheck() {
        this.checkPrices();
        setInterval(() => this.checkPrices(), 5000);
    }

    checkPrices() {
        fetch('/modules/dynamicmargin/ajax.php?action=checkPrices')
            .then(response => response.json())
            .then(data => {
                console.log('Price check response:', data);
                if (data.pricesChanged) {
                    this.updatePrices();
                    this.showNotification(data.message, data.changes);
                }
            })
            .catch(error => console.error('Error checking prices:', error));
    }

    updatePrices() {
        fetch('/modules/dynamicmargin/ajax.php?action=getPrices')
            .then(response => response.json())
            .then(data => {
                console.log('New prices:', data); 
                if (data.success && data.prices) {
                    Object.keys(data.prices).forEach(productId => {
                        const priceSelectors = [
                            `.product-price[data-product-id="${productId}"]`,
                            `#product_${productId} .product-price`,
                            `.js-product-price-${productId}`,
                            `.product-miniature[data-id-product="${productId}"] .price`,
                            `.product-miniature[data-id-product="${productId}"] .regular-price`,
                            `[data-product-id="${productId}"] .price`,
                            `.js-product-miniature[data-id-product="${productId}"] .price`
                        ];
    
                        const selector = priceSelectors.join(', ');
                        const priceElements = document.querySelectorAll(selector);
                        
                        console.log(`Founded ${priceElements.length} elements for product ${productId}`); 
    
                        priceElements.forEach(element => {
                            element.innerHTML = data.prices[productId].display_price;
                            element.classList.add('price-updated');
                            setTimeout(() => {
                                element.classList.remove('price-updated');
                            }, 1000);
                        });
                    });
    
                    if (typeof prestashop !== 'undefined') {
                        prestashop.emit('updatedProduct', {
                            product_prices: data.prices
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error updating prices:', error);
            });
    }

    showNotification(message, changes) {
        const existingNotification = document.querySelector('.dynamic-margin-notification');
        if (existingNotification) {
            existingNotification.remove();
        }

        const notification = document.createElement('div');
        notification.className = 'dynamic-margin-notification';
        
        let html = `
            <div class="notification-content">
                <div class="notification-header">${message}</div>
        `;

        if (changes && changes.length > 0) {
            html += '<div class="price-changes">';
            changes.forEach(change => {
                html += `
                    <div class="price-change-item">
                        <span class="product-name">${change.name}</span>
                        <span class="price-change">
                            <span class="old-price">${change.old_price}</span>
                            →
                            <span class="new-price">${change.new_price}</span>
                        </span>
                    </div>
                `;
            });
            html += '</div>';
        }

        html += `
                <button class="notification-close">&times;</button>
            </div>
        `;

        notification.innerHTML = html;
        document.body.appendChild(notification);

        setTimeout(() => notification.classList.add('show'), 100);

        notification.querySelector('.notification-close').addEventListener('click', () => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        });

        setTimeout(() => {
            if (notification.parentNode) {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }
        }, 5000);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    console.log('Initializing DynamicMargin');
    new DynamicMargin();
});

//# sourceMappingURL=front.js.map