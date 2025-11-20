# Code Examples

This document contains various code examples to test code block removal.

## PHP Code

Here is some PHP code:

```php
<?php
declare(strict_types=1);

namespace App\Controller;

class UsersController extends AppController
{
    public function index()
    {
        $users = $this->Users->find('all');
        $this->set(compact('users'));
    }
}
```

And inline code: `$variable = 'value';` should also be removed.

## JavaScript Example

```javascript
function hello(name) {
    console.log('Hello, ' + name);
    return true;
}
```

## Regular Content

This is regular content that should remain after code block removal.

More inline code here: `echo "test"` and `const x = 10`.

## Shell Commands

```bash
composer install
bin/cake migrations migrate
```

The important text content should be preserved while code is removed.