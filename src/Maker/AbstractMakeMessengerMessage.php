<?php

/*
 * This file is part of the AmineLejmi/MessengerMaker bundle.
 *
 * (c) Mohamed Amine LEJMI <lejmi.amine@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AmineLejmi\MessengerMaker\Maker;

use AmineLejmi\MessengerMaker\Contract\CommandHandlerInterface;
use AmineLejmi\MessengerMaker\Contract\EventHandlerInterface;
use AmineLejmi\MessengerMaker\Contract\QueryHandlerInterface;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Util\UseStatementGenerator;
use Symfony\Bundle\MakerBundle\Util\YamlSourceManipulator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Base command for the maker commands
 */
abstract class AbstractMakeMessengerMessage extends AbstractMaker
{

    const TYPE_COMMAND = 'command';
    const TYPE_EVENT = 'event';
    const TYPE_QUERY = 'query';

    protected $messengerConfig;
    protected $messengerConfigFilePath;

    protected $type = null;

    private $fields = [];

    public function __construct(KernelInterface $kernel)
    {
        $projectDir = $kernel->getProjectDir();
        $this->messengerConfigFilePath = $projectDir . '/config/packages/messenger.yaml';
        if (!file_exists($this->messengerConfigFilePath)) {
            throw new \LogicException('Messenger configuration file is missing.');
        }

        $type = $this->getType();
        if (!in_array($type, [self::TYPE_QUERY, self::TYPE_EVENT, self::TYPE_COMMAND])) {
            throw new \LogicException('Type must be one of [Maker,event,query].');
        }
        $this->type = $type;

        if (is_null($this->type)) {
            throw new MissingInputException('Type of message must be provided.');
        }

        $this->messengerConfig = Yaml::parseFile($this->messengerConfigFilePath);
    }

    abstract public static function getCommandName(): string;

    abstract public static function getCommandDescription(): string;

    abstract public static function getType(): string;


    public function configureCommand(Command $command, InputConfiguration $inputConfig)
    {
        $command
            ->addArgument('name', InputArgument::OPTIONAL, "The name of the $this->type class (e.g. <fg=yellow>SendEmail" . ucfirst($this->type) . "</>)");
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        $messageName = $input->getArgument('name');

        while (!$messageName || !$this->verifyMessageName($messageName)) {
            $io->error('A ' . $this->type . ' can only have ASCII letters and must ends with "' . ucfirst($this->type) . '"');
            $messageName = $io->ask($command->getDefinition()->getArgument('name')->getDescription());
        }
        $messageName = ucfirst($messageName);

        while (file_exists(__DIR__ . '/../Messenger/' . ucfirst($this->type) . 'AbstractMakeMessengerMessage.php/' . $messageName . '.php')) {
            $io->error('There is already a ' . $this->type . ' named ' . $messageName . ', please chose another name.');
            $messageName = ucfirst($io->ask($command->getDefinition()->getArgument('name')->getDescription()));
        }

        $input->setArgument('name', $messageName);


        $command->addArgument('register-as', InputArgument::OPTIONAL);

        $transportsConfig = $this->messengerConfig['framework']['messenger']['transports'] ?? [];
        if ($transportsConfig) {
            $transports = array_keys($transportsConfig);
            array_unshift($transports, 'none');
            $registerAs = $io->askQuestion(
                new ChoiceQuestion(
                    "Register this $this->type as :",
                    $transports,
                    0
                ));
        }

        $input->setArgument('register-as', $registerAs ?? null);

        // Collect the message inputs
        $isFirstField = true;
        $currentFields = [];
        while (true) {
            $newField = $this->askForNextField($io, $currentFields, $isFirstField);
            $isFirstField = false;

            if (null === $newField) {
                break;
            }
            $currentFields[] = $newField['fieldName'];
            $this->fields[] = $newField;
        }
    }

    private function askForNextField(ConsoleStyle $io, array $fields, bool $isFirstField)
    {
        $io->writeln('');

        if ($isFirstField) {
            $questionText = 'New property name (press <return> to stop adding fields)';
        } else {
            $questionText = 'Add another property? Enter the property name (or press <return> to stop adding fields)';
        }

        $fieldName = $io->ask($questionText, null, function ($name) use ($fields) {
            if (\in_array($name, $fields)) {
                throw new \InvalidArgumentException(sprintf('The "%s" property already exists.', $name));
            }

            return $name;
        });

        if (!$fieldName) {
            return null;
        }

        $defaultType = 'string';

        $type = null;
        $allValidTypes = [
            'integer',
            'float',
            'string',
            'boolean',
            'array',
            'datetime',
        ];

        while (null === $type) {
            $question = new Question('Field type (enter <comment>?</comment> to see all types)', $defaultType);
            $question->setAutocompleterValues($allValidTypes);
            $type = $io->askQuestion($question);

            if ('?' === $type) {
                $this->printAvailableTypes($io, $allValidTypes);
                $io->writeln('');

                $type = null;
            } elseif (!\in_array($type, $allValidTypes)) {
                $this->printAvailableTypes($io, $allValidTypes);
                $io->error(sprintf('Invalid type "%s".', $type));
                $io->writeln('');

                $type = null;
            }
        }

        // this is a normal field
        $data = ['fieldName' => $fieldName, 'type' => $type];

        if ($io->confirm('Can this field be null (nullable)', false)) {
            $data['nullable'] = true;
        }

        return $data;
    }

    private function printAvailableTypes(ConsoleStyle $io, array $types)
    {
        if ('Hyper' === getenv('TERM_PROGRAM')) {
            $wizard = 'wizard 🧙';
        } else {
            $wizard = '\\' === \DIRECTORY_SEPARATOR ? 'wizard' : 'wizard 🧙';
        }

        $io->writeln('<info>Main Types</info>');
        foreach ($types as $type) {
            $io->writeln(sprintf('  * <comment>%s</comment>', $type));
        }


    }


    private function verifyMessageName(string $messageName): bool
    {
        return preg_match('/^[a-zA-Z\\\\]+' . ucfirst($this->type) . '$/', $messageName);
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator)
    {
        $messageClassNameDetails = $generator->createClassNameDetails(
            $input->getArgument('name'),
            'Messenger\\' . ucfirst($this->type)
        );
        $handlerClassNameDetails = $generator->createClassNameDetails(
            $input->getArgument('name') . 'Handler',
            'Messenger\\' . ucfirst($this->type) . 'Handler',
            'Handler'
        );

        $arrayFields = [];
        $fieldsProperties = [];
        $fieldsGetters = [];
        $fieldsInitialisations = [];
        $fieldsInitialisationsHandler = [];

        if (count($this->fields) > 0) {
            foreach ($this->fields as $field) {
                if ($field['type'] === 'integer') {
                    $field['type'] = 'int';
                } else if ($field['type'] === 'boolean') {
                    $field['type'] = 'bool';
                } else if ($field['type'] === 'datetime') {
                    $field['type'] = '\Datetime';
                }
                $isNullable = isset($field['nullable']) && $field['nullable'];
                $fieldInput = $isNullable ? '?' : '';
                $fieldInput .= $field['type'] . ' $' . $field['fieldName'];

                $typedProperties = floatval(phpversion()) >= 7.4;

                $fieldProperty = 'private ';
                if ($typedProperties) {
                    if ($isNullable) {
                        $fieldProperty .= '?';
                    }
                    $fieldProperty .= $field['type'] . ' ';
                }
                $fieldProperty .= '$' . $field['fieldName'] . ';';

                $fieldsProperties[] = $fieldProperty;


                $arrayFields[] = $fieldInput;
                $fieldsInitialisations[] = '$this->' . $field['fieldName'] . ' = $' . $field['fieldName'] . ';';
                $fieldsInitialisationsHandler[] = '$' . $field['fieldName'] . ' = $' . $this->type . '->get' .
                    Str::asCamelCase($field['fieldName']) . '();';
                $fieldsGetters[] = $this->generateFieldGetter($field['fieldName'], $field['type'], $isNullable);
            }
        }
        $inputFields = implode(", ", $arrayFields);
        $properties = implode("\n\t", $fieldsProperties);
        $inits = implode("\n\t\t", $fieldsInitialisations);
        $initsHandler = implode("\n\t\t", $fieldsInitialisationsHandler);
        $getters = implode("\n\n", $fieldsGetters);

        $generator->generateClass(
            $messageClassNameDetails->getFullName(),
            __DIR__ . '/Template/message.template.php',
            [
                'fields' => $inputFields,
                'fields_properties' => $properties . "\n",
                'fields_getters' => $getters,
                'fields_init' => $inits . "\n"
            ]
        );

        $interfaces = [
            self::TYPE_COMMAND => CommandHandlerInterface::class,
            self::TYPE_EVENT => EventHandlerInterface::class,
            self::TYPE_QUERY => QueryHandlerInterface::class
        ];

        $useStatements = new UseStatementGenerator([
            $interfaces[$this->type],
            $messageClassNameDetails->getFullName()
        ]);


        $explodedInterfaceName = explode('\\', $interfaces[$this->type]);

        $generator->generateClass(
            $handlerClassNameDetails->getFullName(),
            __DIR__ . '/Template/messageHandler.template.php',
            [
                'use_statements' => $useStatements,
                'message_class_name' => $messageClassNameDetails->getShortName(),
                'interface_name' => end($explodedInterfaceName),
                'message_type' => $this->type,
                'fields_init_handler' => $initsHandler . "\n"
            ]
        );

        if (
            $input->hasArgument('register-as')
            && null !== $input->getArgument('register-as')
            && 'none' !== $input->getArgument('register-as')
        ) {
            $this->updateMessengerConfig(
                $generator,
                $messageClassNameDetails->getFullName(),
                $input->getArgument('register-as')
            );
        }

        $generator->writeChanges();
        $this->writeSuccessMessage($io);
        $io->text([
            "Next: Open your new $this->type class and add the properties you need.",
            "      Then, open the new $this->type handler and do whatever work you want!",
        ]);
    }

    private function generateFieldGetter(string $name, string $type, bool $isNullable)
    {
        $res = "\tpublic function ";
        $res .= "get" . Str::asCamelCase($name);
        $res .= '(): ' . ($isNullable ? '?' : '') . $type . "\n";
        $res .= "\t{\n";
        $res .= "\t\treturn " . '$this->' . $name . ';';
        $res .= "\n\t}";
        return $res;
    }

    private function updateMessengerConfig(
        Generator $generator,
        string    $messageClass,
        string    $registerAs): void
    {
        $yamlManipulator = new YamlSourceManipulator(file_get_contents($this->messengerConfigFilePath));

        if (!isset($this->messengerConfig['framework']['messenger']['routing'])) {
            $this->messengerConfig['framework']['messenger']['routing'] = [];
        }

        $this->messengerConfig['framework']['messenger']['routing'][$messageClass] = $registerAs;

        $yamlManipulator->setData($this->messengerConfig);
        $generator->dumpFile($this->messengerConfigFilePath, $yamlManipulator->getContents());
    }

    public function configureDependencies(DependencyBuilder $dependencies)
    {
        $dependencies->addClassDependency(
            'Symfony\Bundle\MakerBundle\Maker\AbstractMaker',
            'maker-bundle'
        );
        $dependencies->addClassDependency(
            'Symfony\Component\Console\Command\Command',
            'symfony/console'
        );
        $dependencies->addClassDependency(
            'Symfony\Component\Yaml\Yaml',
            'symfony/yaml'
        );
    }
}
