{##
 # Выводит элемент меню, при вложенных элементах уходит в рекурсию
 # @param array item - массив с свойствами эелмента меню
 # @param string current - текущей uri
 # @param integer level - уровень меню, который выводится
 #}
{%- macro menu_item(item, current, level) %}
	{% set url = item['url'] is defined?item['url']:'#' %}
	{% set dropdown = item['dropdown'] is defined %}
	
	<li class="
		{{ current == url?'active':'' }}
		{{ dropdown?' dropdown':'' }}
		{{ dropdown and level>1?' dropdown-submenu':'' }}
	">
		<a 
			{{ dropdown?'class="dropdown-toggle" data-toggle="dropdown"':'' }}
			{{ item['target'] is defined?'target="'~item['target']~'"':'' }} 
			{{ item['title'] is defined?'title="'~item['title']~'"':'' }} 
			href="{{ url }}"
		>
			{{ item['name'] is defined?item['name']:'' }} 
			{{ dropdown and level == 1?'<span class="caret"></span>':'' }}
		</a>
		
		{% if dropdown %}
			<ul class="dropdown-menu" role="menu">
				{% set level = level + 1 %}
				{% for itm in item['dropdown'] %}
					{{ menu_item(itm, current, level) }}
				{% endfor %}
			</ul>
		{% endif %}
	</li>
	
	{{ item['divider'] is defined?'<li class="divider"></li>':'' }}
{%- endmacro %}

{% if menu_left is defined or menu_right is defined %}
<nav class="navbar navbar-default">
	<div class="container">
		<!-- Brand and toggle get grouped for better mobile display -->
		<div class="navbar-header">
			<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar-main">
				<span class="sr-only">Toggle navigation</span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
			</button>
			<a class="navbar-brand" href="/">mmcoexpo</a>
		</div>

		<!-- Collect the nav links, forms, and other content for toggling -->
		<div class="collapse navbar-collapse" id="navbar-main">
			{% if menu_left is defined %}
				<ul class="nav navbar-nav">
					{% for item in menu_left %}
						{{ menu_item(item, this.router.getRewriteUri(), 1) }}
					{% endfor %}
				</ul>
			{% endif %}
			
			{% if menu_right is defined %}
				<ul class="nav navbar-nav navbar-right">
					{% for item in menu_right %}
						{{ menu_item(item, this.router.getRewriteUri(), 1) }}
					{% endfor %}
				</ul>
			{% endif %}
		</div><!-- /.navbar-collapse -->
	</div><!-- /.container-fluid -->
</nav>
{% endif %}