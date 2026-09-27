<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Cargo;
use App\Entity\Funcionario;
use App\Entity\Setor;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** @extends AbstractType<Funcionario> */
final class FuncionarioType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('matricula', TextType::class, ['label' => 'Matrícula', 'attr' => ['inputmode' => 'numeric']])
            ->add('nome', TextType::class, ['label' => 'Nome completo'])
            ->add('email', EmailType::class, ['label' => 'E-mail (login)'])
            ->add('cargo', EntityType::class, ['class' => Cargo::class, 'placeholder' => 'Selecione'])
            ->add('setor', EntityType::class, ['class' => Setor::class, 'placeholder' => 'Selecione', 'label' => 'Lotação'])
            ->add('jornadaDiariaMinutos', ChoiceType::class, [
                'label' => 'Jornada diária',
                'choices' => ['4 horas' => 240, '6 horas' => 360, '7 horas' => 420, '8 horas' => 480],
            ])
            ->add('dataAdmissao', DateType::class, [
                'label' => 'Data de admissão',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('ativo', CheckboxType::class, ['required' => false, 'label' => 'Ativo'])
            ->add('rh', CheckboxType::class, [
                'required' => false,
                'label' => 'Acesso ao painel do RH',
                'help' => 'Permite gerenciar cadastros, fechar competências e avaliar pedidos de qualquer setor.',
            ])
            // Não mapeado: o controller faz o hash antes de gravar.
            ->add('senha', PasswordType::class, [
                'mapped' => false,
                'required' => $options['exigir_senha'],
                'label' => $options['exigir_senha'] ? 'Senha inicial' : 'Nova senha',
                'help' => $options['exigir_senha'] ? null : 'Deixe em branco para manter a senha atual.',
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => array_filter([
                    $options['exigir_senha'] ? new Assert\NotBlank(message: 'Defina uma senha inicial.') : null,
                    new Assert\Length(min: 8, minMessage: 'A senha deve ter ao menos {{ limit }} caracteres.'),
                ]),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Funcionario::class,
            'exigir_senha' => false,
        ]);
        $resolver->setAllowedTypes('exigir_senha', 'bool');
    }
}
