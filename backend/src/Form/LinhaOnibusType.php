<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\LinhaOnibus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<LinhaOnibus> */
final class LinhaOnibusType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('codigo', TextType::class, ['label' => 'Código'])
            ->add('nome', TextType::class, ['label' => 'Itinerário'])
            ->add('tarifa', MoneyType::class, ['currency' => 'BRL', 'input' => 'string'])
            ->add('ativa', CheckboxType::class, [
                'required' => false,
                'help' => 'Linhas inativas não aparecem para novas solicitações.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => LinhaOnibus::class]);
    }
}
