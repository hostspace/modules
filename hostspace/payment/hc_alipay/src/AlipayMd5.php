<?php
/**
 * @file \Drupal\hc_alipay\AlipayMd5
 * MD5
 * 详细：MD5加密
 * 版本：3.3
 * 日期：2012-07-19
 * */
namespace Drupal\hc_alipay;

class AlipayMd5 {
  /**
   * 签名字符串
   * @param $prestr 需要签名的字符串
   * @param $key 私钥
   * return 签名结果
   */
  public function md5Sign($prestr, $key) {
  	$prestr = $prestr . $key;
	  return md5($prestr);
  }

  /**
   * 验证签名
   * @param $prestr 需要签名的字符串
   * @param $sign 签名结果
   * @param $key 私钥
   * return 签名结果
   */
  public function md5Verify($prestr, $sign, $key) {
  	$prestr = $prestr . $key;
  	$mysgin = md5($prestr);
  	if($mysgin == $sign) {
  		return 'true';
  	}
  	else {
  		return false;
  	}
  }
}
