<?php

namespace Drupal\coaching_package\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the coaching package entity edit forms.
 */
class CoachingPackageForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    $entity = $this->getEntity();
    $result = $entity->save();
    $link = $entity->toLink($this->t('View'))->toRenderable();

    $message_arguments = ['%label' => $this->entity->label()];
    $logger_arguments = $message_arguments + ['link' => render($link)];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New coaching package %label has been created.', $message_arguments));
      $this->logger('coaching_package')->notice('Created new coaching package %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The coaching package %label has been updated.', $message_arguments));
      $this->logger('coaching_package')->notice('Updated new coaching package %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.coaching_package.canonical', ['coaching_package' => $entity->id()]);
  }

}
