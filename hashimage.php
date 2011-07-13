<?php
/*
Plugin Name: Hashimage
Plugin URI: http://hasimage.com/
Description: Display image from a hashtag. Exposes this template tag: php echo hashimage('hashtag=unicorn&limit=5'); Cache is 10-12 minutes.
Author: Peder Fjällström
Version: 1.0
Author URI: http://earthpeople.se
*/

class Hashimage{
	function __construct($args){
		$this->hashtag = $args['hashtag'];
		$this->limit = $args['limit'];
		$this->links = array();
		$this->images = array();
		$this->apiurl = 'http://search.twitter.com/search.json?q=&phrase=&ors=twitpic+yfrog+instagr.am+plixi+flic.kr&lang=all&rpp=500&tag=';
		$this->_init();
	}
	private function _init(){
		$resultsjson = @json_decode($this->_fetchurl($this->apiurl.$this->hashtag));
		if(isset($resultsjson) && isset($resultsjson->results)){
			if($resultsjson->results){
				foreach($resultsjson->results as $results){
					$this->_extractlinks($results->text);
				}
			}
		}
		$this->_extractimages();
		$this->images = array_unique($this->images);
		$this->images = array_slice($this->images, 0, $this->limit);
		$this->html = $this->_formathtml($this->images);
	}
	private function _extractimages(){
		if($this->links){
			foreach($this->links as $link){
				if(stristr($link,'yfrog.com')){
					$this->images[] = $this->_extractyfrog($link);
				}else if(stristr($link,'plixi.com')){
					$this->images[] = $this->_extractplixi($link);
				}else if(stristr($link,'instagr.am')){
					$this->images[] = $this->_extractinstagram($link);
				}else if(stristr($link,'twitpic.com')){
					$this->images[] = $this->_extracttwitpic($link);
				}else if(stristr($link,'flic.kr')){
					$this->images[] = $this->_extractflickr($link);
				}
			}
		}
	}
	private function _extractyfrog($link){
		return trim($link,'”."').':iphone';
	}
	private function _extracttwitpic($link){
		$linkparts = explode('/',$link);
		return 'http://twitpic.com/show/large/'.$linkparts[3];
	}
	private function _extractflickr($link){
		$string = $this->_fetchurl($link);
		if(isset($string)){
			preg_match_all('!<img src="(.*)" alt="photo" !', $string, $matches);
			if(isset($matches[1][0])){
				return $matches[1][0];
			}
		}
	}
	private function _extractinstagram($link){
		$link = trim($link);
		$string = $this->_fetchurl($link);
		if(isset($string)){
			preg_match_all('!<img class="photo" src="(.*)" />!', $string, $matches);
			if(isset($matches[1][0]) && !empty($matches[1][0])){
				return $matches[1][0];
			}
		}
	}
	private function _extractplixi($link){
		$string = $this->_fetchurl($link);
		if(isset($string)){
			preg_match_all('! src="(.*)" id="photo"!', $string, $matches);
			if($matches[1][0]){
				return $matches[1][0];
			}
		}
	}
	private function _extractlinks($string){
		preg_match_all('!http?://[\S]+!', $string, $matches);
		if($matches[0]){
			foreach($matches[0] as $match){
				$this->links[] = $match;
			}
		}
	}
	private function _formathtml($images = array()){
		$html = '';
		if($images){
			foreach($images as $image){
				if(!empty($image)){
					$html .= '<a href="'.$image.'"><img src="'.$image.'" alt="picture from twitter hashtag" /></a>'."\n";
				}
			}
		}
		return $html;
	}
	private function _fetchurl($url = null, $ttl = 600){
		if($url){
			if(wp_cache_get(md5($url), 'hashimage') === FALSE){
				$ch = curl_init();
				$options = array(
					CURLOPT_URL => $url,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_CONNECTTIMEOUT => 10,
					CURLOPT_TIMEOUT => 10
				);
				curl_setopt_array($ch, $options);
				$data = curl_exec($ch);
				$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				curl_close($ch);
				if($http_code === 200){
					wp_cache_set(md5($url), $data, 'hashimage', $ttl+rand(0,120));
					return $data;
				}else{
					$result = wp_cache_get(md5($url), 'hashimage');
					return $result;
				}
				return false;
			}else{
				$result = wp_cache_get(md5($url), 'hashimage');
				return $result;
			}
		}
	}
}

function hashimage($args = ''){
	$defaults = array (
 		'hashtag' => 'unicorn',
 		'limit' => '5'
	);	
	$args = wp_parse_args($args, $defaults);
	$hashimage = new Hashimage($args);
	return $hashimage->html;
}

function hashimage_array($args = ''){
	$defaults = array (
 		'hashtag' => 'unicorn',
 		'limit' => '5'
	);	
	$args = wp_parse_args($args, $defaults);
	$hashimage = new Hashimage($args);
	return $hashimage->images;
}

#print_r(hashimage('hashtag=unicorn&limit=5'));