<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Console\Descriptor;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Csrf\Type\FormTypeCsrfExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\ResolvedFormType;
use Symfony\Component\Form\ResolvedFormTypeInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

abstract class AbstractDescriptorTest extends TestCase
{
    /** @dataProvider getDescribeDefaultsTestData */
    public function testDescribeDefaults($object, array $options, $fixtureName)
    {
        $describedObject = $this->getObjectDescription($object, $options);
        $expectedDescription = $this->getExpectedDescription($fixtureName);

        if ('json' === $this->getFormat()) {
            $this->assertEquals(json_encode(json_decode($expectedDescription), JSON_PRETTY_PRINT), json_encode(json_decode($describedObject), JSON_PRETTY_PRINT));
        } else {
            $this->assertEquals(trim($expectedDescription), trim(str_replace(PHP_EOL, "\n", $describedObject)));
        }
    }

    /** @dataProvider getDescribeResolvedFormTypeTestData */
    public function testDescribeResolvedFormType(ResolvedFormTypeInterface $type, array $options, $fixtureName)
    {
        $describedObject = $this->getObjectDescription($type, $options);
        $expectedDescription = $this->getExpectedDescription($fixtureName);

        if ('json' === $this->getFormat()) {
            $this->assertEquals(json_encode(json_decode($expectedDescription), JSON_PRETTY_PRINT), json_encode(json_decode($describedObject), JSON_PRETTY_PRINT));
        } else {
            $this->assertEquals(trim($expectedDescription), trim(str_replace(PHP_EOL, "\n", $describedObject)));
        }
    }

    /** @dataProvider getDescribeOptionTestData */
    public function testDescribeOption(OptionsResolver $optionsResolver, array $options, $fixtureName)
    {
        $describedObject = $this->getObjectDescription($optionsResolver, $options);
        $expectedDescription = $this->getExpectedDescription($fixtureName);

        if ('json' === $this->getFormat()) {
            $this->assertEquals(json_encode(json_decode($expectedDescription), JSON_PRETTY_PRINT), json_encode(json_decode($describedObject), JSON_PRETTY_PRINT));
        } else {
            $this->assertStringMatchesFormat(trim($expectedDescription), trim(str_replace(PHP_EOL, "\n", $describedObject)));
        }
    }

    public function getDescribeDefaultsTestData()
    {
        $options['core_types'] = array('Symfony\Component\Form\Extension\Core\Type\FormType');
        $options['service_types'] = array('Symfony\Bridge\Doctrine\Form\Type\EntityType');
        $options['extensions'] = array('Symfony\Component\Form\Extension\Csrf\Type\FormTypeCsrfExtension');
        $options['guessers'] = array('Symfony\Component\Form\Extension\Validator\ValidatorTypeGuesser');
        $options['decorated'] = false;

        yield array(null, $options, 'defaults_1');
    }

    public function getDescribeResolvedFormTypeTestData()
    {
        $typeExtensions = array(new FormTypeCsrfExtension(new CsrfTokenManager()));
        $parent = new ResolvedFormType(new FormType(), $typeExtensions);

        yield array(new ResolvedFormType(new ChoiceType(), array(), $parent), array('decorated' => false), 'resolved_form_type_1');
        yield array(new ResolvedFormType(new FormType()), array('decorated' => false), 'resolved_form_type_2');
    }

    public function getDescribeOptionTestData()
    {
        $parent = new ResolvedFormType(new FormType());
        $options['decorated'] = false;

        $resolvedType = new ResolvedFormType(new ChoiceType(), array(), $parent);
        $options['type'] = $resolvedType->getInnerType();
        $options['option'] = 'choice_translation_domain';
        yield array($resolvedType->getOptionsResolver(), $options, 'default_option_with_normalizer');

        $resolvedType = new ResolvedFormType(new FooType(), array(), $parent);
        $options['type'] = $resolvedType->getInnerType();
        $options['option'] = 'foo';
        yield array($resolvedType->getOptionsResolver(), $options, 'required_option_with_allowed_values');

        $options['option'] = 'empty_data';
        yield array($resolvedType->getOptionsResolver(), $options, 'overridden_option_with_default_closures');
    }

    abstract protected function getDescriptor();

    abstract protected function getFormat();

    private function getObjectDescription($object, array $options)
    {
        $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, $options['decorated']);
        $io = new SymfonyStyle(new ArrayInput(array()), $output);

        $this->getDescriptor()->describe($io, $object, $options);

        return $output->fetch();
    }

    private function getExpectedDescription($name)
    {
        return file_get_contents($this->getFixtureFilename($name));
    }

    private function getFixtureFilename($name)
    {
        $format = $this->getFormat();
        $baseDir = __DIR__.'/../../Fixtures/Descriptor';
        $file = $baseDir.'/'.$name.'.'.$format;
        if ('\\' === DIRECTORY_SEPARATOR && is_file($winFile = $baseDir.'/win/'.$name.'.'.$format)) {
            return $winFile;
        }

        return $file;
    }
}

class FooType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired('foo');
        $resolver->setDefault('empty_data', function (Options $options, $value) {
            $foo = $options['foo'];

            return function (FormInterface $form) use ($foo) {
                return $form->getConfig()->getCompound() ? array($foo) : $foo;
            };
        });
        $resolver->setAllowedTypes('foo', 'string');
        $resolver->setAllowedValues('foo', array('bar', 'baz'));
        $resolver->setNormalizer('foo', function (Options $options, $value) {
            return (string) $value;
        });
    }
}
