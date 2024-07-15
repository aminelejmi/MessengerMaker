<?= "<?php\n" ?>

namespace <?= $namespace; ?>;

class <?= $class_name."\n"; ?>
{

    <?= $fields_properties; ?>

    public function __construct(<?= $fields; ?>)
    {
        <?= $fields_init; ?>
    }

<?= $fields_getters; ?>

}
