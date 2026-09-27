<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Funcionario;
use App\Entity\Setor;
use App\Repository\FuncionarioRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<Setor> */
final class SetorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('sigla', TextType::class, ['attr' => ['placeholder' => 'Ex.: STI', 'class' => 'uppercase']])
            ->add('nome', TextType::class)
            ->add('chefe', EntityType::class, [
                'class' => Funcionario::class,
                'label' => 'Chefia imediata',
                'required' => false,
                'placeholder' => 'Sem chefia definida (RH avalia os pedidos)',
                'query_builder' => static fn (FuncionarioRepository $r): QueryBuilder => $r->createQueryBuilder('f')
                    ->where('f.ativo = true')
                    ->orderBy('f.nome'),
                'help' => 'Quem aprova justificativas e auxílio-transporte dos servidores deste setor.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Setor::class]);
    }
}
