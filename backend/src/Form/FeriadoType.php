<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Feriado;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<Feriado> */
final class FeriadoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('data', DateType::class, ['widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('descricao', TextType::class, ['label' => 'Descrição', 'attr' => ['placeholder' => 'Ex.: Aniversário da cidade']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Feriado::class]);
    }
}
