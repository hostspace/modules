<?php

/**
 * @file
 * Contains \Drupal\ip\Form\AddMultipleManagementIpForm.
 */

namespace Drupal\ip\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\hostlog\HostLogFactory;

class AddMultipleManagementIpForm extends contententityform {


  /**
   * The custom buainsess ip storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $managementIpStorage;

  /**
   * Constructs a businessIp object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
  * @param \Drupal\Core\Entity\EntityStorageInterface $business_ip_storage
   *   The custom block storage.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(EntityManagerInterface $entity_manager, EntityStorageInterface $managanent_ip_storage) {
    parent::__construct($entity_manager);
    $this->managementIpStorage = $managanent_ip_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $entity_manager = $container->get('entity.manager');
    return new static(
      $entity_manager,
      $entity_manager->getStorage('ipm')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, formstateinterface $form_state) {
    $form =  parent::form($form, $form_state);
    $form['status'] = array(
      '#type' => 'select',
      '#title' => t('Status'),
      '#required' =>TRUE,
      '#default_value' => 1,
      '#weight' => 4,
      '#options' =>ipmStatus()
    );

    $form['group_ip'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'class' => array('container-inline'),
      )
    );
    $form['group_ip']['ip_paragraph'] = array(
      '#type' => 'textfield',
      '#required' =>TRUE,
      '#title' => 'ip',
      '#size' => 20
    );
    $form['group_ip']['ipd_start'] = array(
      '#type' => 'number',
      '#required' =>TRUE,
      '#size' => 5
    );
    $form['group_ip']['ipd_end'] = array(
      '#type' => 'number',
      '#required' =>TRUE,
      '#size' => 5
    );

    $form['server_type'] = array(
      '#type' => 'select',
      '#title' => t('Server type'),
      '#required' =>TRUE,
      '#default_value' => $this->entity->get('status')->value,
      '#weight' => 5,
      '#options' =>ip_server_type()
    );

    $entity_rooms = entity_load_multiple('room');
    $room_options = array();
    foreach ($entity_rooms as $row) {
      $room_options[$row->id()] = $row->label();
    }
    $form['rid'] = array(
      '#type' => 'select',
      '#title' => '所属机房',
      '#required' => true,
      '#options' => $room_options,
      '#weight' => 7,
      '#ajax' => array(
        'callback' => array(get_class($this), 'groupItem'),
        'wrapper' => 'group_item_wrapper',
        'method' => 'html'
      )
    );
    $form['content'] = array(
      '#type' => 'container',
      '#weight' => '8',
      '#id' => 'group_item_wrapper'
    );
    $rid = $form_state->getValue('rid');
    if(!empty($rid)) {
      $group_options = array();
      $groups = \Drupal::service('ip.ipservice')->loadIpGroup(array('rid' => $rid));
      foreach($groups as $group) {
        $group_options[$group->gid] = $group->name;
      }
      $form['content']['group_id'] = array(
        '#type' => 'select',
        '#title' => '所属分组',
        '#required' => true,
        '#options' => $group_options
      );
    } else {
      $form['content']['group_id'] = array();
    }
    return $form;
  }

  /**
   * 业务操作回调函数
   */
  public static function groupItem(array $form, FormStateInterface $form_state) {
    return $form['content']['group_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    //定义一个变量来存储IP组
    $ip_arr = array();

    $ip_paragraph = trim($form_state->getValue('ip_paragraph'));
    $ipd_start = trim($form_state->getValue('ipd_start'));
    $ipd_end = trim($form_state->getValue('ipd_end'));

    if($ipd_start>$ipd_end){
      $k=$ipd_start;
      $ipd_startpb=$ipd_end;
      $ipd_end=$ipd_start;
    }
    for($i=$ipd_start;$i<=$ipd_end;$i++){
      $ips=$ip_paragraph.".".$i;
      if(strcmp(long2ip(sprintf("%u",ip2long($ips))),$ips)){
        $form_state->setErrorByName('ip',$this->t('Please comfire the IP.', array('%ip' => $ips)));
      }
      //根据输入的IP尝试加载业务IP实体若存在则提示错误信息
      $ip_obj = $this->managementIpStorage->loadByProperties(array('ip' => $ips));
      //判断该实体对象是否是一个新的实体对象
      if($this->entity->isNew() && !empty($ip_obj)){
        $form_state->setErrorByName('ip',$this->t('The ip: %ip has been exists.',array('%ip' => $ips)));
      }
      $ip_arr[] = $ips;
    }
    $form_state->setValue('ip_arr',$ip_arr);
  }

  /**
   * Overrides Drupal\Core\Entity\EntityForm::submit().
   */
  public function save(array $form, FormStateInterface $form_state) {
    //获取需要添加的IP数组
    $ip_arr = $form_state->getValue('ip_arr');
    $entity = $this->entity;
     $uuid = \Drupal::service('uuid');
    foreach($ip_arr as $key=>$ip){
      $clone_entity = clone $entity;
      $clone_entity->set('uuid', $uuid->generate());
      $clone_entity->set('ip',$ip);
      $clone_entity->save();
      /** ======================  写入添加业务IP的操作日志 ==========*/
      HostLogFactory::OperationLog('ip')->log($clone_entity, 'insert');
      /**================================================== */
    }
    drupal_set_message('批量添加管理IP成功！');
    $form_state->setRedirectUrl(new Url('ip.ipm.admin'));
  }
}
