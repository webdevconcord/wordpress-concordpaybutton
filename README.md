# Модуль ConcordPay Button для WordPress 

Creator: [ConcordPay](https://concordpay.concord.ua)<br>
Tags: ConcordPay, ConcordPay Button, payment, payment gateway, credit card, Visa, Masterсard, Apple Pay, Google Pay<br>
Requires at least: WordPress 5.5<br>
License: GNU GPL v3.0<br>
License URI: [License](https://opensource.org/licenses/GPL-3.0)

Этот модуль позволит вам принимать платежи через платёжную систему **ConcordPay**.

Для работы модуля **НЕ требуется** наличия сторонних модулей электронной коммерции.

Плагин предоставляет возможность создать на вашем сайте неограниченное число платёжных кнопок **ConcordPay** для
осуществления продаж различных товаров, услуг, подписок, пожертвований и т.п.
Управление заказами при этом осуществляется через Личный кабинет **ConcordPay**. 

## Установка

1. Содержимое архива поместить в папку плагинов **WordPress** ( `{YOUR_SITE}/wp-content/plugins/` ).

2. Зайти в админ раздел сайта `/wp-admin/` и активировать плагин **ConcordPay Button**.

3. Перейти в настройки плагина *«Настройки -> ConcordPay Button»*.

4. Установить необходимые настройки плагина.<br>

   Указать данные, полученные от платёжной системы:
    - *Идентификатор торговца (Merchant ID)*;
    - *Секретный ключ (Secret Key)*.

   Также указать:
    - *Валюту, в которой будут осуществляться платежи*;
    - *Язык страницы оплаты **ConcordPay***;
    - *Обязательные поля с данными покупателя*;
    - *Префикс к обозначению заказа*.

5. Настройка *«Обязательные поля»* отвечает за набор полей,
которые **обязательно** должен будет заполнить покупатель при оформлении заказа.
Настройка устанавливает следующие режимы работы:
    - *«Не требовать»* - После нажатия на кнопку оплаты, покупатель сразу перенаправляется на страницу оплаты **ConcordPay**.
   Данный режим хорошо подходит для осуществления таких платежей как пожертвования, когда не требуется наличия контактных данных с плательщиком;
    - *«Имя + Телефон»*, *«Имя + Email»*, *«Имя + Телефон + Email»* - После нажатия на кнопку оплаты появляется модальное окно с соответствующим набором полей.
   По заполнению всех полей формы, покупатель перенаправляется на страницу оплаты **ConcordPay**.

7. Сохранить настройки модуля.

Модуль готов к работе.

## Использование модуля

**Вариант 1**. В режиме редактирования страницы добавить шорткод вида:

```[cpb name='Product name' price='6.99']```

**Вариант 2**. В режиме редактирования страницы из Меню вставки блоков вставьте **ConcordPay Button** и заполните его поля:
   - *Название товара (Product name)*;
   - *Цена товара (Price)*.

Теперь при отображении страницы будет выведена кнопка переадресации на страницу оплаты **ConcordPay**.

*Модуль ConcordPay Button протестирован для работы с WordPress 6.0 и PHP 7.4.*