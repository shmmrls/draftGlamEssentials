<?php
require_once __DIR__ . '/includes/config.php';
$pageCss = '';
include __DIR__ . '/includes/header.php';
?>

<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:wght@500;600;700&display=swap');
  .faq-wrapper{max-width:960px;margin:60px auto 80px;padding:0 16px}
  .faq-title{font-family:'Playfair Display',serif;font-size:44px;font-weight:600;margin:0 0 10px;color:#121212;text-align:center}
  .faq-sub{font-family:'Inter',system-ui,Segoe UI,Roboto,Arial,sans-serif;letter-spacing:3px;font-size:12px;color:#7a1530;text-transform:uppercase;text-align:center;margin-bottom:18px}
  .faq-divider{width:56px;height:2px;background:#7a1530;margin:0 auto 28px;opacity:.85}
  .faq-list{display:grid;grid-template-columns:1fr;gap:18px}
  .faq-section{border-top:1px solid #eee;padding-top:12px}
  .faq-section-title{font-family:'Montserrat',serif;font-size:35px;margin:10px 0 6px;color:#111; margin-bottom: 20px;}
  .faq-grid{display:grid;grid-template-columns:1fr;gap:20px}
  @media(min-width:900px){ .faq-grid{grid-template-columns:1fr 1fr} }
  .faq-item{border:1px solid #eee;border-radius:10px;padding:12px 14px;background:#fff}
  .faq-q{display:flex;justify-content:space-between;align-items:center;gap:8px;cursor:pointer;font-weight:600}
  .faq-q:hover{color:#7a1530}
  .faq-a{display:none;padding-top:10px;color:#374151}
  .faq-a p{margin:0}
  .faq-toggle{transition:transform .2s ease}
  .faq-item.open .faq-a{display:block}
  .faq-item.open .faq-toggle{transform:rotate(45deg)}
</style>

<main class="faq-wrapper">
  <h1 class="faq-title">Frequently Asked Questions</h1>
  <div class="faq-sub">All about shopping with GlamEssentials</div>
  <div class="faq-divider"></div>

  <section class="faq-list" id="faq">
    <!-- Ordering & Payment -->
    <br>
    <br>
    <div class="faq-section">
      <h2 class="faq-section-title"> | ORDERING & PAYMENT</h2>
      <div class="faq-grid">
        <div class="faq-item">
          <div class="faq-q" role="button" tabindex="0">
            <span>What payment methods do you accept?</span>
            <span class="faq-toggle">+</span>
          </div>
          <div class="faq-a">
            <p>We accept all major credit cards (Visa, Mastercard, American Express), PayPal, and other secure payment methods at checkout.</p>
          </div>
        </div>
        <div class="faq-item">
          <div class="faq-q" role="button" tabindex="0">
            <span>Can I modify or cancel my order?</span>
            <span class="faq-toggle">+</span>
          </div>
          <div class="faq-a">
            <p>Orders can be modified or cancelled within 24 hours of placement. Please contact our customer service team immediately at <a href="mailto:support@glamessentials.com">support@glamessentials.com</a> for assistance.</p>
          </div>
        </div>
        <div class="faq-item">
          <div class="faq-q" role="button" tabindex="0">
            <span>How do I place an order?</span>
            <span class="faq-toggle">+</span>
          </div>
          <div class="faq-a">
            <p>Simply browse our products, add items to your cart, and proceed to checkout. You'll need to create an account or sign in to complete your purchase.</p>
          </div>
        </div>
        <div class="faq-item">
          <div class="faq-q" role="button" tabindex="0">
            <span>Do you offer bulk or wholesale pricing?</span>
            <span class="faq-toggle">+</span>
          </div>
          <div class="faq-a">
            <p>Yes! We offer special pricing for salon professionals and bulk orders. Please contact us at <a href="mailto:sales@glamessentials.com">sales@glamessentials.com</a> for more information.</p>
          </div>
        </div>
      </div>
    </div>
    <br>

    <!-- Shipping & Delivery -->
    <div class="faq-section">
      <h2 class="faq-section-title">| SHIPPING & DELIVERY</h2>
      <div class="faq-grid">
        <div class="faq-item">
          <div class="faq-q" role="button" tabindex="0">
            <span>How long does shipping take?</span>
            <span class="faq-toggle">+</span>
          </div>
          <div class="faq-a">
            <p>Standard shipping typically takes 5–7 business days. Express shipping options are available at checkout for faster delivery.</p>
          </div>
        </div>
        <div class="faq-item">
          <div class="faq-q" role="button" tabindex="0">
            <span>How can I track my order?</span>
            <span class="faq-toggle">+</span>
          </div>
          <div class="faq-a">
            <p>Once your order ships, you'll receive a tracking number via email. You can also track your order by logging into your account.</p>
          </div>
        </div>
        <div class="faq-item">
          <div class="faq-q" role="button" tabindex="0">
            <span>Do you ship internationally?</span>
            <span class="faq-toggle">+</span>
          </div>
          <div class="faq-a">
            <p>Currently, we ship within selected regions. International shipping options may be available—please contact us for details.</p>
          </div>
        </div>
        <div class="faq-item">
          <div class="faq-q" role="button" tabindex="0">
            <span>What if my order arrives damaged?</span>
            <span class="faq-toggle">+</span>
          </div>
          <div class="faq-a">
            <p>We take great care in packaging, but if your order arrives damaged, please contact us within 48 hours with photos, and we'll send a replacement immediately.</p>
          </div>
        </div>
      </div>
    </div>
    <br>

    <!-- Products & Quality -->
    <div class="faq-section">
      <h2 class="faq-section-title">| PRODUCTS & QUALITY</h2>
      <div class="faq-grid">
        <div class="faq-item">
          <div class="faq-q" role="button" tabindex="0">
            <span>Are your products authentic?</span>
            <span class="faq-toggle">+</span>
          </div>
          <div class="faq-a">
            <p>Absolutely. We only source authentic, professional-grade products directly from authorized distributors and manufacturers.</p>
          </div>
        </div>
        <div class="faq-item">
          <div class="faq-q" role="button" tabindex="0">
            <span>Are your products suitable for professional salon use?</span>
            <span class="faq-toggle">+</span>
          </div>
          <div class="faq-a">
            <p>Yes! All our products are curated specifically for professional use and meet industry standards.</p>
          </div>
        </div>
        <div class="faq-item">
          <div class="faq-q" role="button" tabindex="0">
            <span>Do you offer product samples?</span>
            <span class="faq-toggle">+</span>
          </div>
          <div class="faq-a">
            <p>Sample availability varies by product. Please check individual product pages or contact us for specific requests.</p>
          </div>
        </div>
        <div class="faq-item">
          <div class="faq-q" role="button" tabindex="0">
            <span>What if I'm not satisfied with a product?</span>
            <span class="faq-toggle">+</span>
          </div>
          <div class="faq-a">
            <p>Your satisfaction is our priority. Please see our Returns & Exchanges policy for next steps.</p>
          </div>
        </div>
      </div>
    </div>
    <br>

    <!-- Returns & Exchanges -->
    <div class="faq-section">
      <h2 class="faq-section-title">| RETURNS & EXCHANGES</h2>
      <div class="faq-grid">
        <div class="faq-item">
          <div class="faq-q" role="button" tabindex="0">
            <span>What is your return policy?</span>
            <span class="faq-toggle">+</span>
          </div>
          <div class="faq-a">
            <p>We accept returns within 30 days of purchase for unopened products in original packaging. Opened products may be subject to restocking fees.</p>
          </div>
        </div>
        <div class="faq-item">
          <div class="faq-q" role="button" tabindex="0">
            <span>How do I initiate a return?</span>
            <span class="faq-toggle">+</span>
          </div>
          <div class="faq-a">
            <p>Contact our customer service team at <a href="mailto:support@glamessentials.com">support@glamessentials.com</a> with your order number. We'll provide you with return instructions and a return authorization number.</p>
          </div>
        </div>
        <div class="faq-item">
          <div class="faq-q" role="button" tabindex="0">
            <span>When will I receive my refund?</span>
            <span class="faq-toggle">+</span>
          </div>
          <div class="faq-a">
            <p>Refunds are processed within 5–7 business days after we receive your return. Please allow additional time for your bank to process the refund.</p>
          </div>
        </div>
        <div class="faq-item">
          <div class="faq-q" role="button" tabindex="0">
            <span>Do you offer exchanges?</span>
            <span class="faq-toggle">+</span>
          </div>
          <div class="faq-a">
            <p>Yes! If you'd like to exchange a product for a different item or size, please contact our customer service team.</p>
          </div>
        </div>
      </div>
    </div>
    <br>

    <!-- Account & Privacy -->
    <div class="faq-section">
      <h2 class="faq-section-title">| ACCOUNT & PRIVACY</h2>
      <div class="faq-grid">
        <div class="faq-item">
          <div class="faq-q" role="button" tabindex="0">
            <span>Do I need an account to shop?</span>
            <span class="faq-toggle">+</span>
          </div>
          <div class="faq-a">
            <p>While you can browse as a guest, creating an account allows you to track orders, save favorites, and enjoy exclusive member benefits.</p>
          </div>
        </div>
        <div class="faq-item">
          <div class="faq-q" role="button" tabindex="0">
            <span>Is my personal information secure?</span>
            <span class="faq-toggle">+</span>
          </div>
          <div class="faq-a">
            <p>Yes. We use industry-standard encryption and security measures to protect your personal and payment information.</p>
          </div>
        </div>
        <div class="faq-item">
          <div class="faq-q" role="button" tabindex="0">
            <span>How do I sign up for exclusive deals?</span>
            <span class="faq-toggle">+</span>
          </div>
          <div class="faq-a">
            <p>Click "SIGN UP NOW TO START SHOPPING" in our header banner or subscribe to our newsletter at the bottom of any page.</p>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<script>
  document.querySelectorAll('.faq-q').forEach(function(q){
    q.addEventListener('click', function(){ q.parentElement.classList.toggle('open'); });
    q.addEventListener('keydown', function(e){ if (e.key==='Enter' || e.key===' ') { e.preventDefault(); q.parentElement.classList.toggle('open'); } });
  });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>