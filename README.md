## Dintero Hyvä Checkout Module for Magento 2
This module provides compatibility between the Dintero Checkout for Magento 2 and the Hyvä Checkout. It ensures a seamless, high-performance payment experience by implementing the Dintero payment gateway using Magewire and Alpine.js.

Requirements
- Magento 2.4.x
- Hyvä Checkout (License required)
- Dintero Checkout Magento V2 (The base Dintero module)
- - ```bash composer require dintero/hp```
- Hyva_ThemeFallback (Required for Embedded Express)

## Installation
Install the module via Composer:

```Bash
composer require dintero/magento2-hyva-checkout
```

Enable the module and update the database:

```bash
bin/magento module:enable Dintero_HyvaCheckout
bin/magento setup:upgrade
bin/magento setup:di:compile
```

