<?= "<?php\n" ?>

namespace <?= $namespace; ?>;

<?= $use_statements; ?>

class <?= $class_name; ?> implements <?= $interface_name."\n"; ?>
{

    public function __construct()
    {
    }

    public function __invoke(<?= $message_class_name; ?> $<?= $message_type; ?>)
    {
        <?= $fields_init_handler; ?>
    }

}
