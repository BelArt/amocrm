{##
 # Вывод ошибок валидации поля, если они существуют
 # @param field
 #}
{%- macro form_error(field) %}
	{% if field.getForm().hasMessagesFor(field.getName()) %}
        <ul class="help-block">
            {% for error in field.getForm().getMessagesFor(field.getName()) %}
                <li>{{ error }}</li>
            {% endfor %}
        </ul>
    {% endif %}
{%- endmacro %}

{##
 # Вывод label для элемента если он существует
 # @param field
 #}
{%- macro form_label(field) %}
	{% if field.getLabel() %}
        <label for="{{ field.getName() }}">{{ field.getLabel() }}</label>
    {% endif %}
{%- endmacro %}

{##
 # Вывод поля без кастомизации
 # @param field
 # @param attr
 #}
{%- macro form_field(attr) %}
	{% set field = attr[0] %}
	{% set attr = attr[1] | default([]) %}
	
	{{ form_label(field) }}
	{{ field.render(attr) }}
{%- endmacro %}

{##
 # Вывод text-поля и многих других похожих (числовые, select, даты, пароли)
 # @param field
 # @param attr
 #}
{%- macro form_text(attr) %}
	{% set field = attr[0] %}
	{% set attr = attr[1] | default([]) %}
	
	{{ form_label(field) }}
	{% set attr = attr|merge(['class': 'form-control']) %}
	{{ field.render(attr) }}
{%- endmacro %}

{##
 # Вывод textarea
 # @param field
 # @param attr
 #}
{%- macro form_textarea(attr) %}
	{% set field = attr[0] %}
	{% set attr = attr[1] | default([]) %}
	
	{{ form_label(field) }}
	{% set attr = attr|merge(['class': 'form-control', 'rows': '5']) %}
	{{ field.render(attr) }}
{%- endmacro %}

{##
 # Вывод checkbox
 # @param field
 # @param attr
 #}
{%- macro form_check(attr) %}
	{% set field = attr[0] %}
	{% set attr = attr[1] | default([]) %}
	<div class="checkbox">
		<label>
			{{ field.render(attr) }} {{ field.getLabel() }}
		</label>
	</div>
{%- endmacro %}

{##
 # Вывод указанного поля с кастомизацией в зависимости от типа поля
 # @param field
 # @param attr
 #}
{%- macro form_row(attr) %}
	{% set field = attr[0] %}
	<div class="form-group{% if field.getForm().hasMessagesFor(field.getName()) %} has-error{% endif %}">
		
		{% if 
			field | instanceof('\Phalcon\Forms\Element\Text') or
			field | instanceof('\Phalcon\Forms\Element\Password') or
			field | instanceof('\Phalcon\Forms\Element\Select') or
			field | instanceof('\Phalcon\Forms\Element\Date') or
			field | instanceof('\Phalcon\Forms\Element\Numeric')
		%}
			{{ form_text(attr) }}
		{% elseif field | instanceof('\Phalcon\Forms\Element\Check') %}
			{{ form_check(attr) }}
		{% elseif field | instanceof('\Phalcon\Forms\Element\Textarea') %}
			{{ form_textarea(attr) }}
		{% else %}
			{{ form_field(attr) }}
		{% endif %}
		
		{{ form_error(field) }}
	</div>
{%- endmacro %}

{##
 # Вывод всех полей формы
 # @param form
 #}
{%- macro form_rows(form) %}
	{% for field in form %}
		{{ form_row([field]) }}
	{% endfor %}
{%- endmacro %}