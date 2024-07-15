# AmineLejmi/MessengerMaker

**The MessengerMaker** is a Symfony bundle that simplifies the creation
and registration of messages, distinctly separating queries, commands,
and events.

### Key Features:

- **Automated Message Creation:** Effortlessly creates and segregates
  queries, events, and commands.
- **Input and Getter Generation:** Automatically generates inputs
  and getter methods for messages.
- **Bus Registration:** Seamlessly registers messages in the
  specified message transport.

This bundle adds a layer on top of Symfony/Messenger, leveraging its
configuration. For more details, please refer to the
[Symfony Messenger Component documentation](https://symfony.com/doc/current/components/messenger.html).


Installation
============

Make sure Composer is installed globally, as explained in the
[installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

Applications that use Symfony Flex
----------------------------------

Open a command console, enter your project directory and execute:

```console
composer require amine-lejmi/messenger-maker
```

Applications that don't use Symfony Flex
----------------------------------------

### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```console
composer require amine-lejmi/messenger-maker
```

### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `config/bundles.php` file of your project:

```php
// config/bundles.php

return [
    // ...
    AmineLejmi\MessengerMaker\MessengerMakerBundle::class => ['all' => true],
];
```

Usage
============

The bundle provides three different console commands
for creating commands, queries, and events:

### 1- Creating a new command

```console
php bin/console make:messenger:command <command-name>
```

**Note:** The `<command-name>` must end with "Command".

### 2- Creating a new query

```console
php bin/console make:messenger:query <query-name>
```

**Note:** The `<query-name>` must end with "Query".

### 3- Creating a new event

```console
php bin/console make:messenger:event <event-name>
```

**Note:** The `<event-name>` must end with "Event".

## How It Works
### Executing the command

For each of these commands, the console will prompt
you:
1. to choose the corresponding transport if configured in `config/messenger.yaml`:

```shell
$ php bin/console make:messenger:command SendEmailCommand

 Register this command as : [none]:
  [0] none
  [1] low-priority
  [2] high-priority
 > 
```
**Note:** After choosing a transport, the bundle will automatically
register the message in routing section inside 
`config/packages/messenger.yaml`

```yaml
framework:
  # ...
  messenger:
    # ...
    routing:
        # ...
        App\Messenger\Command\SendEmailCommand: low-priority
        # ...
```

2. To add Any additional inputs required for the specific message

```shell

New property name (press <return> to stop adding fields):
> 

Field type (enter ? to see all types) [string]:
> 

Can this field be null (nullable) (yes/no) [no]:
> 
```

Follow the prompts to complete the creation of 
the command, query, or event.

### Creation of the files
Depending on which command you executed, those folders will be created 
containing the corresponding files:
```
📦project_dir
 ┣ 📂...
 ┣ 📂 src
 ┃ ┗ 📂 Messenger
 ┃    ┗ 📂 Command
 ┃    ┗ 📂 CommandHandler
 ┃    ┗ 📂 Event
 ┃    ┗ 📂 EventHandler
 ┃    ┗ 📂 Query
 ┃    ┗ 📂 QueryHandler
```

**Note:** : Handlers implement interfaces to allow them to be dispatched
to the corresponding bus:
- query.bus
- event.bus
- command.bus

### Examples

```php
<?php

namespace App\Messenger\Command;

class SendEmailCommand
{
    private string $address;
    private ?string $message;

    public function __construct(string $address, ?string $message)
    {
        $this->address = $address;
        $this->message = $message;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }
}
```

```php
<?php

namespace App\Messenger\CommandHandler;

use AmineLejmi\MessengerMaker\Contract\CommandHandlerInterface;
use App\Messenger\Command\SendEmailCommand;

class SendEmailCommandHandler implements CommandHandlerInterface
{
    public function __construct()
    {
    }

    public function __invoke(SendEmailCommand $command)
    {
        $address = $command->getAddress();
        $message = $command->getMessage();
        
        // Do something with your variables 
    }
}

```




















