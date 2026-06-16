import { startStimulusApp } from '@symfony/stimulus-bundle';
import AvatarUploadController from './controllers/avatar_upload_controller.js';
import CartController from './controllers/cart_controller.js';
import LanguageController from './controllers/language_controller.js';
import LicensesCarouselController from './controllers/licenses_carousel_controller.js';
import ProductDetailController from './controllers/product_detail_controller.js';
import ProfileAuthController from './controllers/profile_auth_controller.js';
import ShopFiltersController from './controllers/shop_filters_controller.js';

const app = startStimulusApp();
app.register('avatar-upload', AvatarUploadController);
app.register('cart', CartController);
app.register('language', LanguageController);
app.register('licenses-carousel', LicensesCarouselController);
app.register('product-detail', ProductDetailController);
app.register('profile-auth', ProfileAuthController);
app.register('shop-filters', ShopFiltersController);
