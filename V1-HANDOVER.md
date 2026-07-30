# TB247 Gadget Lab — Báo cáo bàn giao V1

Phạm vi V1: Amazon only. Landing Page `/d/{ASIN}` trên WordPress (Plugin `TB247 Deal Manager` + Theme `tb247-gadget-lab`), tích hợp 1 điểm vào Discord Bot `BotFun-API`. Không có tính năng mới ngoài phạm vi đã thống nhất trong plan ban đầu.

---

## 1. Rà soát mã nguồn lần cuối (kết quả)

| Hạng mục | Kết quả |
|---|---|
| Lint cú pháp PHP (13 file plugin + 12 file theme) | `php -l` — tất cả PASS |
| Lint cú pháp JS (`copy-jan.js`, `wordpress-sync.js`) | `node --check` — PASS |
| Require/include trong plugin | 10 file trong `includes/` khớp đúng 10 dòng `require_once` trong `tb247-deal-manager.php` — không thừa, không thiếu |
| Class/interface định nghĩa | 10/10 class+interface đều được sử dụng ít nhất 1 nơi (đã trace bằng grep `new X`, `X::method`, `implements X`, `array('X','method')`) |
| Hàm trong theme (`tb247_theme_setup`, `tb247_enqueue_assets`, `tb247_is_deal_page`) | Cả 3 đều được hook/gọi đúng chỗ, không có hàm mồ côi |
| `require("./wordpress-sync")` trong `index.js` | Dùng đúng 1 lần tại điểm tích hợp, không có require thừa |
| Debug/leftover code (`var_dump`, `print_r`, `error_log`, `console.log` thử nghiệm, `TODO/FIXME`) | Không còn sót trong plugin/theme/bot |
| Giá trị test hardcode (`test-secret-key-123`, `localhost:8098`, ASIN test...) | Không lọt vào code plugin/theme/bot — chỉ còn trong `.env` (sẽ thay khi deploy) |
| File test tạm (`wp-test/`, `test-wordpress-sync.js`) | Nằm hoàn toàn trong thư mục scratchpad hệ thống, **không nằm trong project**, không được bàn giao |

**2 lỗi phát hiện trong quá trình test và đã sửa trước khi bàn giao** (đã báo cáo lúc phát hiện, nhắc lại ở đây để hồ sơ đầy đủ):
1. `class-deal-rewrite.php` — `template_include()` chỉ set `global $post` mà chưa ghi đè `$wp_query` chính → trang landing hiển thị nhầm bài viết mặc định. Đã sửa bằng `prime_main_query()`.
2. `assets/js/copy-jan.js` — thiếu `.catch()` khi Clipboard API bị trình duyệt từ chối quyền → nút không phản hồi. Đã thêm fallback textarea/`execCommand`.

Không phát hiện thêm lỗi nào khác ở lần rà soát cuối này.

---

## 2. Danh sách file đã **thêm mới**

### Plugin — `wordpress/wp-content/plugins/tb247-deal-manager/`
```
tb247-deal-manager.php
uninstall.php
includes/class-plugin.php
includes/cpt/class-deal-post-type.php
includes/routing/class-deal-rewrite.php
includes/rest/class-auth.php
includes/rest/class-rest-controller.php
includes/services/class-deal-service.php
includes/marketplaces/interface-marketplace.php
includes/marketplaces/class-marketplace-registry.php
includes/marketplaces/class-amazon-marketplace.php
includes/seo/class-deal-seo.php
templates/single-deal-fallback.php
```

### Theme — `wordpress/wp-content/themes/tb247-gadget-lab/`
```
style.css
functions.php
inc/setup.php
inc/enqueue.php
header.php
footer.php
front-page.php
page.php
single-deal.php
index.php
404.php
assets/css/deal.css
assets/js/copy-jan.js
```

### Bot — `BotFun-API/`
```
wordpress-sync.js
```

## 3. Danh sách file đã **sửa**

| File | Sửa gì |
|---|---|
| `BotFun-API/index.js` | +1 dòng `require("./wordpress-sync")`; thay khối `if (displayLink) embed.setURL(displayLink)` bằng logic gọi `syncAmazonDealToWordPress()` + fallback (xem diff mục 4) |
| `BotFun-API/.env.example` | +2 biến `WORDPRESS_API_URL`, `WORDPRESS_API_KEY` |
| `BotFun-API/.env` | +2 biến trên (đang trỏ tạm vào WordPress test cục bộ đã tắt — **phải cập nhật khi deploy**, xem mục 5) |
| `BotFun-API/.gitignore` | File mới, loại trừ `node_modules/`, `.env`, `*.log`, `.DS_Store` |

Không file nào khác trong `BotFun-API` bị đụng tới — toàn bộ logic đọc Amazon, gọi KaitoriX, và cấu trúc Discord Embed giữ nguyên 100%.

---

## 4. Những thay đổi chính

- **WordPress không phải blog**: CPT `deal` ẩn khỏi search/trang chủ, mỗi ASIN = 1 bài, URL cố định `/d/{ASIN}` (không dùng `?p=123`).
- **REST API riêng** (`POST /wp-json/tb247/v1/deals`), xác thực bằng header `X-TB247-API-KEY` so với hằng số `TB247_API_KEY` trong `wp-config.php` — không dùng tài khoản WordPress, không phụ thuộc plugin ngoài.
- **Dedupe theo ASIN**: gửi lại cùng ASIN → cập nhật bài cũ, không tạo bài mới (đã kiểm chứng bằng dữ liệu thật, kể cả khi bot gửi trùng 1 ASIN 2 lần).
- **Kiến trúc mở rộng sẵn cho Rakuten/Yahoo**: chỉ cần thêm 1 class implement `TB247_DM_Marketplace` + đăng ký vào `Marketplace_Registry` — không đụng REST Controller, Deal Service, hay CPT.
- **Landing Page tối giản**: ảnh, tên, giá, JAN + nút copy, nút mua — không review/ngày/giá Kaitori/comment/share/tags theo đúng yêu cầu.
- **OG/Twitter tags** tự sinh trong Plugin (không phụ thuộc theme), không cần plugin SEO ngoài.
- **Tích hợp bot tối thiểu, không rủi ro**: 1 file mới + 1 điểm gọi duy nhất trong `index.js`; nếu WordPress lỗi/timeout (giới hạn 5 giây) → tự fallback về link Amazon như trước khi có tích hợp, Discord không bao giờ bị gián đoạn — đã kiểm chứng qua test thật (0 lỗi trên 9 sản phẩm thật xử lý qua Discord).

---

## 5. Các bước khôi phục (rollback) nếu cần

### Rollback phía Bot
```bash
cd ~/Desktop/BotFun-API
git log --oneline          # xem lại commit baseline
git diff 139f4e4 -- index.js .env.example   # xem lại đúng những gì đã đổi
git checkout 139f4e4 -- index.js .env.example   # khôi phục về bản trước khi tích hợp
rm wordpress-sync.js        # xoá file tích hợp mới
```
(Xoá 2 dòng `WORDPRESS_API_URL`/`WORDPRESS_API_KEY` trong `.env` thật nếu muốn dọn sạch hoàn toàn — không bắt buộc, để đó cũng không ảnh hưởng gì nếu chưa `require` file `wordpress-sync.js`.)

### Rollback phía WordPress
- **Tắt tạm**: wp-admin → Plugins → Deactivate `TB247 Deal Manager` (Landing Page sẽ ngưng hoạt động, dữ liệu deal vẫn còn nguyên trong DB).
- **Gỡ hẳn**: Deactivate rồi Delete plugin — `uninstall.php` chỉ dọn rewrite rules, **không xoá dữ liệu deal đã lưu**.
- **Đổi theme**: Appearance → Themes → active lại theme khác bất kỳ lúc nào; Landing Page vẫn hoạt động nhờ template dự phòng có sẵn trong Plugin (`templates/single-deal-fallback.php`).

---

## 6. Các bước triển khai lên hosting thật

1. **Kiểm tra hosting**: PHP 7.4+, WordPress mới nhất, hỗ trợ Permalinks đẹp (mod_rewrite/Nginx rewrite), có HTTPS/SSL (bắt buộc cho nút copy JAN).
2. **Cài WordPress** lên hosting (1-click installer của hosting, hoặc cài thủ công).
3. **Đóng gói & upload**:
   ```bash
   cd "wordpress/wp-content/plugins" && zip -r tb247-deal-manager.zip tb247-deal-manager
   cd "../themes" && zip -r tb247-gadget-lab.zip tb247-gadget-lab
   ```
   Upload qua wp-admin (Plugins/Themes → Add New → Upload) hoặc FTP/SFTP vào `wp-content/plugins/` và `wp-content/themes/`.
4. **Activate** Plugin trước, rồi Activate Theme.
5. **Khai báo API key thật** trong `wp-config.php` (trước dòng `/* That's all, stop editing! */`):
   ```php
   define( 'TB247_API_KEY', 'CHUOI_BI_MAT_MOI_KHONG_TRUNG_VOI_GIA_TRI_TEST' );
   ```
6. **Bật Permalinks**: Settings → Permalinks → chọn khác "Plain" (khuyến nghị "Post name") → Save Changes (bắt buộc để `/d/{ASIN}` hoạt động).
7. **Tạo trang & menu**: tạo Page "Home", "お問い合わせ" (và 2 trang còn lại nếu cần nội dung riêng) → Settings → Reading → đặt "Home" làm static front page → Appearance → Menus → tạo menu 4 mục (ホーム, 随時セール情報, おすすめ商品, お問い合わせ) → gán vào vị trí "Menu chính".
8. **Test REST API thật**:
   ```bash
   curl -X POST https://domain-that.com/wp-json/tb247/v1/deals \
     -H "Content-Type: application/json" \
     -H "X-TB247-API-KEY: CHUOI_BI_MAT_MOI" \
     -d '{"marketplace":"amazon","asin":"B0GFVJ4K82","product_name":"Test","jan":"1234567890123","sale_price":1000,"image":"https://example.com/x.jpg","product_url":"https://www.amazon.co.jp/dp/B0GFVJ4K82","affiliate_url":"https://www.amazon.co.jp/dp/B0GFVJ4K82?tag=tb247fun-22"}'
   ```
   Kiểm tra `landing_url` trả về mở đúng, rồi xoá bài test này trong wp-admin (Deals → Trash).
9. **Cập nhật `.env` thật của bot**:
   ```
   WORDPRESS_API_URL=https://domain-that.com/wp-json/tb247/v1/deals
   WORDPRESS_API_KEY=CHUOI_BI_MAT_MOI
   ```
   (phải khớp chính xác giá trị đã khai báo ở bước 5 — hiện `.env` đang trỏ vào `localhost:8098` test đã tắt, **bắt buộc phải đổi** trước khi chạy production).
10. **Khởi động lại bot** (`start.command`) để nạp `.env` mới, dán 1 link Amazon thật vào Discord để xác nhận Embed trỏ đúng Landing Page trên domain thật.

Không cần migrate database từ môi trường test — WordPress trên hosting bắt đầu trống, deal sẽ tự được tạo khi bot chạy thật.
