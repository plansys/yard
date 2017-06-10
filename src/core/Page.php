<?php
use PHPHtmlParser\Dom;

class Page {
	use JsConvert;

	public $alias;
	public $path;
	public $store = [];
	public $masterpage = false;
	public $isRoot = true;
	public $showDeps = true;

	private $placeholder = null;

	public function mapStore() {return [];}
	public function mapAction() {return [];}
	public function js() {}
	public function css() {}
	public function render() {}

	public function url($path) {
		$ref = new ReflectionClass($this);
		$dir = dirname($ref->getFileName());
		$url = substr($dir, strlen(Setting::getRootPath()));
		return (str_replace(DIRECTORY_SEPARATOR, '/', $url) . '/' . $path);
	}

	public function renderPage($dev = true) {
		$baseUrl = Yii::app()->request->getBaseUrl(true);
		$head = [];
		$head[] = '<title>' . Setting::get('app.name') . '</title>';

		if ($dev) {
			$host = Setting::get('app.host');
			ob_start();
			include dirname(__FILE__) . DIRECTORY_SEPARATOR . 'template/html_dev.php';
			$body = ob_get_clean();
		} else {
			$dir = realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..') . DIRECTORY_SEPARATOR . 'ui' . DIRECTORY_SEPARATOR;
			$body = file_get_contents($dir . 'index.html');
			$body = explode("\n", $body);
			$body = "\t" . implode("\n\t", $body);
		}

		$body .= "\n\t<script>" . $this->renderInitJs() . "\n\t</script>\n";

		# process body array
		$head = "\t" . implode("\n\t", $head);
		include "template/html.php";
	}

	public function renderInitJS() {
		$baseUrl = Yii::app()->request->getBaseUrl(true);
		$basePath = Yii::app()->request->getBaseUrl();
		ob_start();
		include "template/initjs.php";
		return ob_get_clean();
	}

	public function __construct($alias, $path, $isRoot = true, $placeholder = null) {
		$this->alias = $alias;
		$this->path = $path;
		$this->isRoot = $isRoot;
		$this->placeholder = $placeholder;
	}

	public static function load($alias, $isRoot = true, $showDeps = true) {
		$path = Page::resolve($alias);
		$namespace = Page::resolveNamespace($alias);
		$class = $namespace . '\\' . str_replace(".php", "", basename($path));
		
		if (!class_exists($class, false)) {
			if ($path == '') {
				throw new Exception('Page not found: ' . $alias);
			}
			require($path);
		}

		if (!class_exists($class, false)) {
			throw new Exception("Class `{$class}` not found in: " . $path);
		}

		$new = new $class($alias, $path, $isRoot);
		$new->showDeps = $showDeps;

		if ($isRoot && is_string($new->masterpage)) {
			$master = null;
			$new->isRoot = false;

			$masterPath = Page::resolve($new->masterpage);
			$masterClass = str_replace(".php", "", basename($masterPath)) . "Page";

			if (!class_exists($masterClass, false)) {
				include $masterPath;
			}

			if (!class_exists($masterClass, false)) {
				throw new Exception("Class `{$masterClass}` not found in: " . $masterPath);
			}

			$master = new $masterClass($new->masterpage, $masterPath, true, $new);

			return $master;
		}
		return $new;
	}

	private function getIsPlansys() {
		return strpos($this->path, Yii::getPathOfAlias('application')) === 0;
	}

	public function renderConf($indent = 0, $indentAdder = 2) {
		## declare vars that will be attached to conf
		$component = $this->renderComponent($indent + $indentAdder);
		$css = trim($this->css()) == "" ? "false" : "true";
		$js = $this->renderInternalJS($indent + $indentAdder - 1);
		$baseUrl = Yii::app()->request->getBaseUrl(true);

		$mapStore = $this->renderMapStore();
		$mapAction = $this->renderMapAction();

		$page = Page::load($this->alias, false, false);
		$loaders = $this->loadLoaders($page);
		if (count($loaders) > 0) {
			$loaders = $this->toJs($loaders);
		} else {
			$loaders = '[]';
		}
		if ($this->isRoot || $this->showDeps) {
			$dependencies = $this->loadDeps();
			
			if ($this->isRoot) {
				$reducers = $this->renderReduxReducers();
				$actionCreators = $this->renderReduxActions();
				$sagas = $this->renderReduxSagas();
			}

			$placeholder = '';
			if (!is_null($this->placeholder)) {
				$pdeps = $this->placeholder->loadDeps();
				foreach ($pdeps['pages'] as $k => $p) {
					if (!isset($dependencies['pages'][$k])) {
						$dependencies['pages'][$k] = $p;
					}
				}
				foreach ($pdeps['elements'] as $k => $p) {
					if (!in_array($p, $dependencies['elements'])) {
						$dependencies['elements'][] = $p;
					}
				}

				$dependencies['pages']['"' . $this->placeholder->alias . '"'] = 'js:' . $this->placeholder->renderConf();
				$placeholder = json_encode($this->placeholder->alias);
			}

			$dependencies = $this->toJs($dependencies);
		}

		## load conf template
		ob_start();
		include "template/conf.php";
		$conf = ob_get_clean();

		## prettify conf
		$pad = "";
		if ($indent > 0) {
			$pad = str_pad("    ", ($indent) * 4);
		}
		$conf = explode("\n", $conf);
		$conf = implode("\n" . $pad, $conf);
		
		return trim($conf);
	}

	public function renderDeps() {
		if ($this->placeholder) {
			echo $this->toJs($this->placeholder->loadDeps($this->placeholder->alias));
		} else {
			echo $this->toJs($this->loadDeps($this->alias));
		}
	}

	public function loadDeps($alias = "", $flatten = true, $dependencies = false) {
		$page = $this;
		if ($alias != "") {
			$page = Page::load($alias, false);
		}

		$tags = array_keys($this->loadTags($page));

		$isRoot = false;
		if ($dependencies === false) {
			$isRoot = true;
			$dependencies = [
				'pages' => [],
				'elements' => [],
			];
		}

		foreach ($tags as $tag) {
			if (strpos($tag, 'Page:') === 0) {
				$p = substr($tag, 5);
				if (strpos(trim($p), "js:") === 0) {
					continue;
				}

				if (!isset($dependencies['pages']['"' . $p . '"'])) {
					if ($p != $this->alias) {
						if ($flatten) {
							$dependencies['pages']['"' . $p . '"'] = true;
							$dependencies = $this->loadDeps($p, $flatten, $dependencies);
						} else {
							$dependencies['pages']['"' . $p . '"'] = $this->loadDeps($p, $flatten, $dependencies);
						}
					}
				}
			} else {
				if (!in_array($tag, $dependencies['elements'])) {
					$dependencies['elements'][] = $tag;
				}
			}
		}

		if ($isRoot && $flatten) {
			foreach ($dependencies['pages'] as $p => $v) {
				if ($p != $this->alias) {
					$sp = Page::load(trim($p, '"'), false);
					$dependencies['pages'][$p] = "\n\t\t\tjs:" . $sp->renderConf(3, -1) . "\n\t\t";
				}
			}
		}

		return $dependencies;
	}

	public function loadLoaders($page, $tags = false) {
		if ($tags === false) {
			$tag = $page->parseRender();
			$tags = [];

			if ($tag[0] == 'Page') {
				$tags[] = $tag[1]['name'];
			}

			if (count($tag) == 2 && is_array($tag[1]) && !Helper::is_assoc($tag[1])) {
				$tags = $this->loadLoaders($tag[1], $tags);
			} else if (count($tag) == 3 && is_array($tag[2]) && !Helper::is_assoc($tag[2])) {
				$tags = $this->loadLoaders($tag[2], $tags);
			}
		} else {
			foreach ($page as $tag) {

				if ($tag[0] == 'Page') {
					if (strpos(trim($tag[1]['name']), "js:") === 0) {
						continue;
					}
					$tags[] = $tag[1]['name'];
				}

				if (count($tag) == 2 && is_array($tag[1]) && !Helper::is_assoc($tag[1])) {
					$tags = $this->loadLoaders($tag[1], $tags);
				} else if (count($tag) == 3 && is_array($tag[2]) && !Helper::is_assoc($tag[2])) {
					$tags = $this->loadLoaders($tag[2], $tags);
				}
			}
		}

		return $tags;
	}
	
	public function loadTags($page, $tags = false) {
		if ($tags === false) {
			$tag = $page->parseRender();
			$tags = [];
			$tags[$tag[0]] = true;

			if (count($tag) == 2 && is_array($tag[1]) && !Helper::is_assoc($tag[1])) {
				$tags = $this->loadTags($tag[1], $tags);
			} else if (count($tag) == 3 && is_array($tag[2]) && !Helper::is_assoc($tag[2])) {
				$tags = $this->loadTags($tag[2], $tags);
			}
		} else {
			foreach ($page as $tag) {
				if (!is_array($tag)) continue;

				if ($tag[0] == 'Page') {
					$tags[$tag[0] . ":" . $tag[1]['name']] = true;
				} else {
					$tags[$tag[0]] = true;
				}

				if (count($tag) == 2 && is_array($tag[1]) && !Helper::is_assoc($tag[1])) {
					$tags = $this->loadTags($tag[1], $tags);
				} else if (count($tag) == 3 && is_array($tag[2]) && !Helper::is_assoc($tag[2])) {
					$tags = $this->loadTags($tag[2], $tags);
				}
			}
		}

		return $tags;
	}

	public function renderInternalJS($indent = 0) {
		$pad = "";
		if ($indent > 0) {
			$pad = str_pad("    ", ($indent) * 4);
		}

		$js = $this->js();
		$js = explode("\n", $js);
		$js = implode("\n" . $pad, $js);
		return trim($js) . "\n";
	}

	private $_reducers = [];
	private function getReducers() {
		if (!is_array($this->store)) return [];
		if (count($this->_reducers) > 0) return $this->_reducers;
		
		$reducers = [];
		foreach ($this->store as $storeRaw) {
			$store = Helper::explodeFirst(".", $storeRaw);
			$reducer = Helper::explodeLast(".", $storeRaw);

			$storepath = Yii::getPathOfAlias('app.redux.' . $store);
			if (!is_dir($storepath)) {
				$storepath = Yii::getPathOfAlias('application.redux.' . $store);

				if (!is_dir($storepath)) {
					throw new Exception ("Redux Store [{$store}] not found!");
				}
			}

			if ($reducer != "*") {
				$reducerPath = $storepath . DIRECTORY_SEPARATOR . $reducer . 'Reducer.php';
				if (is_file($reducerPath)) {
					$class = $reducer . 'Reducer';
					if (!class_exists($class, false)) {
						require($reducerPath);
					}
					$reducers[strtolower($reducer)] = new $class;
				} else {
					throw new Exception ("Redux Store Reducer [{$store}.{$reducer}] not found in {$reducerPath}!");
				}
			} else {
				$reducerPath = $storepath . DIRECTORY_SEPARATOR . '*Reducer.php';
				$glob = Helper::globRecursive($reducerPath);
				foreach ($glob as $file) {
					if (is_dir($file)) continue;
					$class = str_replace(".php", "", basename($file));
					$reducer = str_replace("Reducer.php", "", basename($file));

					if (!class_exists($class, false)) {
						require($file);
					}	
					$reducers[strtolower($reducer)] = [
						'class' => new $class,
						'file' => $file,
						'store' => $store
					];
				}
			}
		}
		

		$this->_reducers = $reducers;
		return $reducers;
	}

	public function renderReduxActions() {
		$reducers = $this->getReducers();
		if (empty($reducers)) return;

		$list = [];
		foreach ($reducers as $k=>$r) {
			$glob = glob(dirname($r['file']) . DIRECTORY_SEPARATOR . "*Action.php");
			foreach ($glob as $file) {
				require($file);
				$class = str_replace(".php", "", basename($file));
				if (class_exists($class, false)) {
					$item = new $class;
					
					if (!isset($actions[$r['store']])) {
						$list[$r['store']] = [];
					}
					$list[$r['store']][$k] = $item->actionCreators();
					$res = &$list[$r['store']][$k];
					foreach  ($res as $kr=>$kv) {
						if (is_array($kv)) {
							
							if (!isset($kv['params'])) {
								$kv['params'] = "";
							}

							$script = isset($kv['script']) ? substr($kv['script'],3) : '';

							$return = $this->toJs([
								'type' => $kv['type'],
								'payload' => @$kv['payload']
							]);
							$res[$kr] = "js: function({$kv['params']}) {
					{$script}
					return {$return};
				}";
						}
					}
				}
			}
		}

		return "return " . $this->toJs($list) . ";";
	}

	public function renderReduxReducers() {
		$reducers = $this->getReducers();
		if (empty($reducers)) return;

		$list = [];
		foreach ($reducers as $k=>$r) {
			if (!isset($list[$r['store']])) {
				$list[$r['store']] = [];
			}
			$list[$r['store']][$k] = $r['class']->list();
		}

		return "return " . $this->toJs($list) . ";";
	}

	public function renderReduxSagas() {
		$reducers = $this->getReducers();
		if (empty($reducers)) return;

		$list = [];
		foreach ($reducers as $k=>$r) {
			$glob = glob(dirname($r['file']) . DIRECTORY_SEPARATOR . "*Saga.php");
			foreach ($glob as $file) {
				require($file);
				$class = str_replace(".php", "", basename($file));
				if (class_exists($class, false)) {
					$item = new $class;
					
					if (!isset($actions[$r['store']])) {
						$list[$r['store']] = [];
					}
					$list[$r['store']][$k] = $item->sagas();
				}
			}
		}

		return "return " . $this->toJs($list) . ";";
	}

	public function parseRender() {
		$render = $this->render();
		if (is_array($render)) {
			return $render;
		} else if (is_string($render)) {
			try {
				# format string, so that DomDocument does not choke on 'weird' mark up
				$render = str_replace('&', '~^AND^~', $render); #replace '&' with something.

				# parse the dom
				$fdom = FluentDom::load($render, 'text/xml', ['libxml'=> LIBXML_COMPACT ]);
				$json = new HSerializer($fdom);
				
				# turn it to string again, and then un-format it
				$jsontxt = str_replace('~^AND^~', '&', $json->__toString()); #un-replace '&' 

				# decode it into array
				return json_decode($jsontxt, true);
			} catch(Exception $e) {
				$render = explode("\n", $render);
				$row = Helper::explodeFirst(" ", Helper::explodeLast('in line ',$e->getMessage()));
				$col = Helper::explodeFirst(":", Helper::explodeLast('character ',$e->getMessage())) + 1;
				$tab = "    ";

				$errors = [];

				$style = ['style' => ['borderBottom' => '1px solid red']];
				$errors[] = ['div', $style, $e->getMessage()];
				foreach ($render as $ln => $line) {
					$num = str_pad($ln + 1, 4, " ", STR_PAD_LEFT) . " | ";
					if ($ln == $row -1) {
						$style = ['style' => ['background' => 'red', 'color' => 'white']];
						$errors[] = ['div', $style, $num . str_replace("\t", $tab, $line)];

						for($k = 1; $k < strlen($line); $k++) {
							if ($k != $col) {
								$line[$k] = $line[$k] === "\t" ? $tab : " ";
							} else {
								$line[$k] = "^";
							}
						}

						$errors[] = ['div', "     | " . str_replace("\t", $tab, $line)];
					} else {
						$errors[] = ['div', $num . str_replace("\t", $tab, $line)];
					}
				}

				return ['pre', ['style' => [
					'border' => '1px solid red', 
					'position'=> 'fixed',
					'background' => 'white',
					'color' => 'black',
					'zIndex' => '9999',
					'fontSize' => '11px'
				]], $errors];
			}
		}
	}

	public function renderMapStore() {
		$mapstore = $this->toJs($this->mapStore());
		if (trim($mapstore) == "{}") return "";

		return "return " . $mapstore;
	}

	public function renderMapAction() {
		$mapaction = $this->toJs($this->mapAction());
		if (trim($mapaction) == "{}") return "";

		return "return " . $mapaction;
	}

	public function renderCSS($indent = 0) {
		$css = $this->css();

		if (!$css) {
			return "";
		}

		$pad = "";
		if ($indent > 0) {
			$pad = str_pad("    ", ($indent) * 4);
		}
		$css = explode("\n", $css);
		$css = implode("\n" . $pad, $css);
		return $pad . $css;
	}

	public function renderComponent($indent = 0) {
		return $this->renderComponentInternal($this->parseRender(), $indent);
	}

	private function renderComponentInternal($content, $level = 0) {
		$attr = '';
		$child = '';
		$tag = $content[0];

		$renderSub = function ($ct, $level) {
			$els = [];
			foreach ($ct as $el) {
				$els[] = $this->renderComponentInternal($el, $level + 1);
			}
			$bc = "";
			if ($level > 0) {
				$bc = str_pad("    ", ($level) * 4);
			}
			return ", [\n" . str_pad("    ", ($level + 1) * 4) . implode(",", $els) . "\n" . $bc . "]";
		};
		$count = count($content);
		if ($count > 1) {
			if ($count >= 2) {
				if (is_array($content[1])) {
					if ($tag == 'js') {
						$jsstr = "";
						foreach ($content[1] as $jsc) {
							if (is_string($jsc)) {
								$jsstr .= $jsc;
							} else if (is_array($jsc)) {
								$jsstr .= $this->renderComponentInternal($jsc, $level + 1);
							}
						}

						$noReturn = false;
						$noReturnIf = ["if", "while", "return", "for", "switch", "console", "var"];
						$jsstr = trim($jsstr);
						foreach ($noReturnIf as $nr) {
							if (strpos($jsstr, $nr) === 0) {
								$noReturn = true;
								break;
							}
						}
						if ($noReturn) {
							if (strpos($jsstr, "return ") === false && strpos($jsstr, "console") !== 0) {
								$jscode = explode("\n", $jsstr);
								foreach ($jscode as $ln=>$v) {
									$num = str_pad($ln + 1, 4, " ", STR_PAD_LEFT) . " | ";
									$jscode[$ln] = $num . $v;
								}
								$jsstr = json_encode($jscode);
								$jsstr = "console.error('The <js> should return <el> or string in Page [{$this->alias}]:', \"\\n\\n\" + {$jsstr}.join(\"\\n\"))";
							}
						} else {
							$jsstr = " return (" . $jsstr . ")";
						}
						$attr = "," . " function(h) { $jsstr }";
					} else {
						if (Helper::is_assoc($content[1])) {
							$attr = ", " . $this->toJS($content[1]);
						} else if ($count == 2) {
							$attr = $renderSub($content[1], $level);
						}
					}
				} else {
	                $attr = ',' . json_encode($content[1]);
				}

				if ($count == 3) {
					if (is_array($content[2]) && !Helper::is_assoc($content[2])) {
						$child = $renderSub($content[2], $level);
					} else {
						$child = "," . json_encode($content[2]);
					}
				}
			}
		}

		return "h('{$tag}'{$attr}{$child})";
	}

	public static function resolve($alias) {
		$path = ['app.pages', 'application.pages'];
		foreach ($path as $p) {
			$f = Yii::getPathOfAlias($p . '.' . $alias) . ".php";
			if (file_exists($f)) {
				return $f;
			}
		}
		return false;
	}

	public static function resolveNamespace($alias) {
		$path = ['app.pages', 'application.pages'];
		foreach ($path as $p) {
			$f = Yii::getPathOfAlias($p . '.' . $alias) . ".php";
			if (file_exists($f)) {
				return '\\Pages' . substr(dirname($f), strlen(Yii::getPathOfAlias($p)));
			}
		}
		return false;
	}

}
