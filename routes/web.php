<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Api\RazorpayCheckoutController;
use App\Http\Controllers\Api\RazorpayWebhookController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ProfessionalsController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\FormProtectionController;
use App\Http\Controllers\CatalogueRequestController;
use App\Http\Controllers\DealerApplicationController;
use App\Http\Controllers\VendorProposalController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\AboutController;
use App\Http\Controllers\RailingsController;
use App\Http\Controllers\StudioController;
use App\Http\Controllers\ShopPageController;
use App\Http\Controllers\MirrorFramesController;
use App\Http\Controllers\CollectionGalleryController;
use App\Http\Controllers\AccountAuthController;
use App\Http\Controllers\AccountDashboardController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductAdminController;
use App\Http\Controllers\Admin\OrderAdminController;
use App\Http\Controllers\Admin\LeadAdminController;
use App\Http\Controllers\Admin\CategoryAdminController;
use App\Http\Controllers\Admin\ProjectAdminController;
use App\Http\Controllers\Admin\BlogAdminController;
use App\Http\Controllers\Admin\ExhibitionAdminController;
use App\Http\Controllers\Admin\ProfessionalApplicationAdminController;
use App\Http\Controllers\Admin\RailingQuoteAdminController;
use App\Http\Controllers\Admin\CustomerAdminController;
use App\Http\Controllers\Admin\SiteSettingAdminController;
use App\Http\Controllers\Admin\LegalPageAdminController;
use App\Http\Controllers\Admin\MediaAdminController;
use App\Http\Controllers\Admin\ServiceAdminController;
use App\Http\Controllers\Admin\CollectionPageAdminController;
use App\Http\Controllers\Admin\IndependentLandingAdminController;
use App\Http\Controllers\CortenSteelController;

// Public storefront
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::redirect('/preview.html', '/');
Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
Route::get('/shop/mirror-frames', [MirrorFramesController::class, 'index'])->name('shop.mirror-frames.index');
Route::get('/shop/mirror-frames/{design}', [MirrorFramesController::class, 'show'])->name('shop.mirror-frames.show');
Route::get('/shop/{slug}', [ShopPageController::class, 'show'])->name('shop.show');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/add/{product}', [CartController::class, 'add'])->middleware('throttle:cart')->name('cart.add');
Route::patch('/cart/update/{product}', [CartController::class, 'update'])->middleware('throttle:cart')->name('cart.update');
Route::delete('/cart/remove/{product}', [CartController::class, 'remove'])->name('cart.remove');

Route::middleware('checkout.customer')->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');
    Route::get('/checkout/pay/{order}', [PaymentController::class, 'show'])->name('checkout.pay');
    Route::post('/checkout/pay/{order}', [PaymentController::class, 'verify'])->middleware('throttle:checkout')->name('checkout.pay.verify');
    Route::get('/checkout/success/{order}', [CheckoutController::class, 'success'])->name('checkout.success');

    Route::prefix('api')->middleware('throttle:checkout')->group(function () {
        Route::post('/create-order', [RazorpayCheckoutController::class, 'createOrder'])->name('api.create-order');
        Route::post('/verify-payment', [RazorpayCheckoutController::class, 'verifyPayment'])->name('api.verify-payment');
    });
});

Route::post('/webhooks/razorpay', RazorpayWebhookController::class)->name('webhooks.razorpay');

Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
Route::get('/corten-steel', [CortenSteelController::class, 'show'])->name('corten-steel.show');
Route::redirect('/services/corten-steel-facade', '/corten-steel', 301);
Route::redirect('/services/bespoke-metal-furniture', '/shop/bespoke-metal-furniture');
Route::redirect('/services/partitions', '/studio/pvd-partitions');
Route::redirect('/services/slim-profile-door-system', '/studio/slim-profile-door-systems');
Route::redirect('/services/main-entrance-pvd-doors', '/studio/main-entrance-pvd-doors');
Route::redirect('/services/rack-systems-metal-pvd', '/studio/metal-pvd-rack-systems');
Route::get('/services/{slug}', [ServiceController::class, 'show'])->name('services.show');
Route::get('/services/{serviceSlug}/{designSlug}', [ServiceController::class, 'design'])->name('services.design');

Route::get('/studio', [StudioController::class, 'index'])->name('studio.index');
Route::get('/studio/{slug}', [StudioController::class, 'show'])
    ->whereIn('slug', \App\Support\StorefrontRoutes::studioUrlSlugs())
    ->name('studio.show');

Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
Route::get('/projects/{slug}', [ProjectController::class, 'show'])->name('projects.show');

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

Route::get('/custom-order', [LeadController::class, 'create'])->name('leads.create');
Route::post('/leads', [LeadController::class, 'store'])->middleware('throttle:general-enquiry')->name('leads.store');
Route::post('/custom-order', [LeadController::class, 'store'])->middleware('throttle:general-enquiry');

Route::get('/contact', [ContactController::class, 'index'])->name('contact.index');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:general-enquiry')->name('contact.store');

Route::get('/form-protection/token/{formKey}', [FormProtectionController::class, 'token'])
    ->middleware('throttle:60,1')
    ->name('form-protection.token');

Route::get('/vendor-proposals', [VendorProposalController::class, 'show'])->name('vendor-proposal.index');
Route::post('/vendor-proposals', [VendorProposalController::class, 'store'])->middleware('throttle:vendor-proposal')->name('vendor-proposal.store');

Route::get('/catalogue', [CatalogueRequestController::class, 'show'])->name('catalogue.index');
Route::post('/catalogue', [CatalogueRequestController::class, 'store'])->middleware('throttle:catalogue-request')->name('catalogue.store');
Route::get('/catalogue/download/{token}', [CatalogueRequestController::class, 'download'])->name('catalogue.download');

Route::get('/dealer-application', [DealerApplicationController::class, 'show'])->name('dealer.index');
Route::post('/dealer-application', [DealerApplicationController::class, 'store'])->middleware('throttle:professional-application')->name('dealer.store');

Route::get('/railings', [RailingsController::class, 'index'])->name('railings.index');
Route::redirect('/studio/railings', '/railings');

// Legacy collection URLs → shop (preserve named routes for old links)
Route::get('/collections/mirror-frames', fn () => redirect()->route('shop.mirror-frames.index', [], 301))->name('collections.mirror-frames.index');
Route::get('/collections/mirror-frames/{design}', fn (string $design) => redirect()->route('shop.mirror-frames.show', $design, 301))->name('collections.mirror-frames.show');
Route::get('/collections/{slug}', function (string $slug) {
    return redirect()->route('shop.show', $slug, 301);
})->whereIn('slug', CollectionGalleryController::slugs())
    ->name('collections.gallery.index');

Route::get('/about', [AboutController::class, 'index'])->name('about');
Route::get('/professionals', [ProfessionalsController::class, 'index'])->name('professionals.index');
Route::view('/team', 'pages.team')->name('team');
Route::prefix('account')->name('account.')->middleware('customer.guest')->group(function () {
    Route::get('/login', [AccountAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AccountAuthController::class, 'sendLoginOtp'])->middleware('throttle:otp-send')->name('login.send');
    Route::post('/login/email', [AccountAuthController::class, 'loginWithEmail'])->middleware('throttle:auth')->name('login.email');
    Route::post('/login/mobile', [AccountAuthController::class, 'loginWithMobilePassword'])->middleware('throttle:auth')->name('login.mobile');
    Route::get('/register', [AccountAuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AccountAuthController::class, 'sendRegisterOtp'])->middleware('throttle:otp-send')->name('register.send');
    Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('social.redirect');
    Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('social.callback');
    Route::get('/forgot', [AccountAuthController::class, 'showForgot'])->name('forgot');
    Route::post('/forgot', [AccountAuthController::class, 'sendForgotOtp'])->middleware('throttle:otp-send')->name('forgot.send');
    Route::post('/forgot/reset', [AccountAuthController::class, 'resetForgotPassword'])->middleware('throttle:auth')->name('forgot.reset');
});

Route::get('/account/verify-otp', [AccountAuthController::class, 'showVerifyOtp'])->name('account.verify');
Route::post('/account/verify-otp', [AccountAuthController::class, 'verifyOtp'])->middleware('throttle:otp-verify')->name('account.verify.submit');
Route::post('/account/resend-otp', [AccountAuthController::class, 'resendOtp'])->middleware('throttle:otp-send')->name('account.resend');

Route::middleware('customer')->group(function () {
    Route::get('/account', [AccountDashboardController::class, 'index'])->name('account');
    Route::post('/account/profile', [AccountDashboardController::class, 'updateProfile'])->name('account.profile.update');
    Route::post('/account/addresses', [AccountDashboardController::class, 'storeAddress'])->name('account.addresses.store');
    Route::delete('/account/addresses/{address}', [AccountDashboardController::class, 'destroyAddress'])->name('account.addresses.destroy');
    Route::post('/account/logout', [AccountAuthController::class, 'logout'])->name('account.logout');
});

Route::get('/privacy-policy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/terms-and-conditions', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/shipping-delivery-policy', [LegalController::class, 'shipping'])->name('legal.shipping');
Route::get('/cancellation-refund-policy', [LegalController::class, 'cancellation'])->name('legal.cancellation');
Route::get('/warranty-returns-policy', [LegalController::class, 'warranty'])->name('legal.warranty');
Route::get('/contact-grievance-policy', [LegalController::class, 'grievance'])->name('legal.grievance');

Route::redirect('/privacy', '/privacy-policy');
Route::redirect('/terms', '/terms-and-conditions');
Route::redirect('/shipping-returns', '/shipping-delivery-policy');
Route::redirect('/shipping', '/shipping-delivery-policy');

// Admin
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:auth')->name('login.submit');
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

    Route::middleware('admin')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::resource('products', ProductAdminController::class)->except(['show']);
        Route::resource('orders', OrderAdminController::class)->only(['index', 'show', 'update']);
        Route::resource('leads', LeadAdminController::class)->only(['index', 'show', 'update', 'destroy']);
        Route::post('leads/{lead}/false-positive', [LeadAdminController::class, 'markFalsePositive'])->name('leads.false-positive');
        Route::post('leads/{lead}/qualified', [LeadAdminController::class, 'markQualified'])->name('leads.qualified');
        Route::post('leads/{lead}/mark-vendor', [LeadAdminController::class, 'markVendor'])->name('leads.mark-vendor');
        Route::post('leads/{lead}/mark-spam', [LeadAdminController::class, 'markSpam'])->name('leads.mark-spam');
        Route::post('leads/{lead}/merge-duplicate', [LeadAdminController::class, 'mergeDuplicate'])->name('leads.merge-duplicate');
        Route::post('leads/{lead}/block-identity', [LeadAdminController::class, 'blockIdentity'])->name('leads.block-identity');
        Route::post('leads/{lead}/restore', [LeadAdminController::class, 'restore'])->name('leads.restore');
        Route::get('leads/{lead}/attachment', [LeadAdminController::class, 'downloadAttachment'])->name('leads.attachment');

        Route::resource('categories', CategoryAdminController::class)->except(['show']);
        Route::post('categories/sync', [CategoryAdminController::class, 'sync'])->name('categories.sync');
        Route::post('categories/reorder', [CategoryAdminController::class, 'reorder'])->name('categories.reorder');
        Route::post('categories/{category}/move/{direction}', [CategoryAdminController::class, 'move'])->name('categories.move');

        Route::resource('projects', ProjectAdminController::class)->except(['show']);

        Route::get('blog', [BlogAdminController::class, 'index'])->name('blog.index');
        Route::get('blog/create', [BlogAdminController::class, 'create'])->name('blog.create');
        Route::post('blog', [BlogAdminController::class, 'store'])->name('blog.store');
        Route::get('blog/{post}/edit', [BlogAdminController::class, 'edit'])->name('blog.edit');
        Route::put('blog/{post}', [BlogAdminController::class, 'update'])->name('blog.update');
        Route::delete('blog/{post}', [BlogAdminController::class, 'destroy'])->name('blog.destroy');

        Route::resource('exhibitions', ExhibitionAdminController::class)->except(['show']);
        Route::post('exhibitions/reorder', [ExhibitionAdminController::class, 'reorder'])->name('exhibitions.reorder');
        Route::post('exhibitions/{exhibition}/move/{direction}', [ExhibitionAdminController::class, 'move'])->name('exhibitions.move');

        Route::resource('services', ServiceAdminController::class)->except(['show']);

        Route::get('collection-pages', [CollectionPageAdminController::class, 'index'])->name('collection-pages.index');
        Route::get('collection-pages/{slug}/edit', [CollectionPageAdminController::class, 'edit'])->name('collection-pages.edit');
        Route::put('collection-pages/{slug}', [CollectionPageAdminController::class, 'update'])->name('collection-pages.update');

        Route::get('independent-pages', [IndependentLandingAdminController::class, 'index'])->name('independent-pages.index');
        Route::get('independent-pages/{slug}/edit', [IndependentLandingAdminController::class, 'edit'])->name('independent-pages.edit');
        Route::put('independent-pages/{slug}', [IndependentLandingAdminController::class, 'update'])->name('independent-pages.update');

        Route::get('professional-applications', [ProfessionalApplicationAdminController::class, 'index'])->name('professional-applications.index');
        Route::get('professional-applications/{professional_application}', [ProfessionalApplicationAdminController::class, 'show'])->name('professional-applications.show');
        Route::put('professional-applications/{professional_application}', [ProfessionalApplicationAdminController::class, 'update'])->name('professional-applications.update');
        Route::get('professional-applications/{lead}/attachment', [LeadAdminController::class, 'downloadAttachment'])->name('professional-applications.attachment');

        Route::get('railing-quotes', [RailingQuoteAdminController::class, 'index'])->name('railing-quotes.index');
        Route::get('railing-quotes/{railing_quote}', [RailingQuoteAdminController::class, 'show'])->name('railing-quotes.show');
        Route::put('railing-quotes/{railing_quote}', [RailingQuoteAdminController::class, 'update'])->name('railing-quotes.update');
        Route::get('railing-quotes/{lead}/attachment', [LeadAdminController::class, 'downloadAttachment'])->name('railing-quotes.attachment');

        Route::get('customers', [CustomerAdminController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [CustomerAdminController::class, 'show'])->name('customers.show');
        Route::put('customers/{customer}', [CustomerAdminController::class, 'update'])->name('customers.update');

        Route::get('settings', [SiteSettingAdminController::class, 'edit'])->name('settings.edit');
        Route::match(['put', 'post'], 'settings', [SiteSettingAdminController::class, 'update'])->name('settings.update');

        Route::get('legal', [LegalPageAdminController::class, 'index'])->name('legal.index');
        Route::get('legal/{legal}/edit', [LegalPageAdminController::class, 'edit'])->name('legal.edit');
        Route::put('legal/{legal}', [LegalPageAdminController::class, 'update'])->name('legal.update');

        Route::get('media', [MediaAdminController::class, 'index'])->name('media.index');
        Route::post('media', [MediaAdminController::class, 'store'])->name('media.store');
        Route::put('media/{medium}', [MediaAdminController::class, 'update'])->name('media.update');
        Route::delete('media/{medium}', [MediaAdminController::class, 'destroy'])->name('media.destroy');
        Route::get('media/{medium}/download', [MediaAdminController::class, 'download'])->name('media.download');
    });
});
