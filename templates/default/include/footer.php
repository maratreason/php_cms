</main>
  <footer class="footer">
    <div class="container">
      <div class="footer__wrapper">
        <div class="footer__top">
          <div class="footer__top_logo">
            <img src="<?=$this->img($this->set['img'])?>" alt="">
          </div>
          <div class="footer__top_menu">
            <ul>

              <li>
                <a href="http://somesite.ru/catalog/"><span>Каталог</span></a>
              </li>

              <li>
                <a href="http://somesite.ru/about/"><span>О нас</span></a>
              </li>

              <li>
                <a href="http://somesite.ru/delivery/"><span>Доставка и оплата</span></a>
              </li>

              <li>
                <a href="http://somesite.ru/contacts/"><span>Контакты</span></a>
              </li>

              <li>
                <a href="http://somesite.ru/news/"><span>Новости</span></a>
              </li>

              <li>
                <a href="http://somesite.ru/sitemap/"><span>Карта сайта</span></a>
              </li>

            </ul>
          </div>
          <div class="footer__top_contacts">
            <div><a href="mailto:test@test.ru">test@test.ru</a></div>
            <div><a href="tel:+74842750204">+7 (4842) 75-02-04</a></div>
            <div><a class="js-callback">Связаться с нами</a></div>
          </div>
        </div>
        <div class="footer__bottom">
          <div class="footer__bottom_copy">Copyright</div>
        </div>
      </div>
    </div>
  </footer>

  <div class="hide-elems">
    <svg>
      <defs>
        <linearGradient id="rainbow" x1="0" y1="0" x2="50%" y2="50%">
          <stop offset="0%" stop-color="#7282bc" />
          <stop offset="100%" stop-color="#7abfcc" />
        </linearGradient>
      </defs>
    </svg>
  </div>

  <?php $this->getScripts();?>

  <?php if (!empty($_SESSION['res']['answer'])): ?>
    <div class="wq-message__wrap"><?=$_SESSION['res']['answer']?></div>
  <?php endif; ?>

  <?php unset($_SESSION['res']); ?>

</body>

</html>