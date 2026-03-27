(function() {
    const button = () =>{
        const bridgeConnectionUninstallElement = document.getElementById('bridgeConnectionUninstall');
        const newElement = document.createElement('a');
        newElement.textContent = 'GO TO DATAFEEDWATCH';
        newElement.setAttribute('class', 'dfw-btn');
        newElement.setAttribute('href', 'https://app.datafeedwatch.com/platforms/router?source_name=woocommerce');
        newElement.setAttribute('target', '_blank');
        bridgeConnectionUninstallElement.parentNode.appendChild(newElement);
    };

    const h1 = () => {
        const logoElement = document.querySelector('.bridge a img');
        const newElement = document.createElement('h1');
        newElement.textContent = 'DataFeedWatch Connector for WooCommerce';
        logoElement.parentNode.parentNode.classList.add('dfw');
        logoElement.parentNode.parentNode.appendChild(newElement);
    };

    const message = () => {
        const messageElement = document.querySelector('.bridge--connectormessage');
        const bridgeElement = document.querySelector('.bridge');
        const beforeElement = bridgeElement.querySelector('.bridge--container');
        bridgeElement.insertBefore(messageElement, beforeElement);
    }

    const removeElements = () => {
        document.querySelector('.text-center p').remove();
    }

    h1();
    button();
    message();
    removeElements();
})();
