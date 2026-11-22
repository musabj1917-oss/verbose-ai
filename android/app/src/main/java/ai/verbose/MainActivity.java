package ai.verbose;

import android.annotation.SuppressLint;
import android.app.Activity;
import android.content.ClipData;
import android.content.Context;
import android.content.Intent;
import android.graphics.Bitmap;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Vibrator;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.view.Window;
import android.view.WindowManager;
import android.webkit.CookieManager;
import android.webkit.JavascriptInterface;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;

public class MainActivity extends Activity {

    private WebView webView;
    private ProgressBar progressBar;
    private LinearLayout errorView;
    private ValueCallback<Uri[]> mUploadMessage;
    private static final int FILE_CHOOSER_RESULT_CODE = 1001;
    private static final String APP_URL = "https://verboseai.vercel.app";

    @SuppressLint({"SetJavaScriptEnabled", "AddJavascriptInterface"})
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        // Dark theme status bar and navigation bar matching Verbose AI (#0c0d11)
        Window window = getWindow();
        window.addFlags(WindowManager.LayoutParams.FLAG_DRAWS_SYSTEM_BAR_BACKGROUNDS);
        window.clearFlags(WindowManager.LayoutParams.FLAG_TRANSLUCENT_STATUS);
        window.setStatusBarColor(0xFF0C0D11);
        window.setNavigationBarColor(0xFF0C0D11);

        // Root container
        FrameLayout rootLayout = new FrameLayout(this);
        rootLayout.setBackgroundColor(0xFF0C0D11);
        rootLayout.setLayoutParams(new ViewGroup.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            ViewGroup.LayoutParams.MATCH_PARENT
        ));

        // 1. WebView Setup
        webView = new WebView(this);
        webView.setLayoutParams(new FrameLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            ViewGroup.LayoutParams.MATCH_PARENT
        ));
        webView.setBackgroundColor(0xFF0C0D11);

        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setDatabaseEnabled(true);
        settings.setCacheMode(WebSettings.LOAD_DEFAULT);
        settings.setMediaPlaybackRequiresUserGesture(false);
        settings.setAllowFileAccess(true);
        settings.setAllowContentAccess(true);
        settings.setGeolocationEnabled(false);
        settings.setLoadWithOverviewMode(true);
        settings.setUseWideViewPort(true);
        settings.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);

        // Normalize User Agent to allow Google OAuth login in-app
        String ua = settings.getUserAgentString();
        if (ua != null && ua.contains("; wv")) {
            settings.setUserAgentString(ua.replace("; wv", ""));
        }

        CookieManager.getInstance().setAcceptCookie(true);
        CookieManager.getInstance().setAcceptThirdPartyCookies(webView, true);

        // Native JavaScript Bridge
        webView.addJavascriptInterface(new VerboseNativeBridge(this), "AndroidBridge");

        // 2. Native Slim Loading Progress Bar at top
        progressBar = new ProgressBar(this, null, android.R.attr.progressBarStyleHorizontal);
        FrameLayout.LayoutParams pbParams = new FrameLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            dpToPx(3)
        );
        pbParams.gravity = Gravity.TOP;
        progressBar.setLayoutParams(pbParams);
        progressBar.setMax(100);
        progressBar.setProgress(0);
        progressBar.setVisibility(View.GONE);

        // 3. Native Offline / Error View
        errorView = createErrorView();
        errorView.setVisibility(View.GONE);

        rootLayout.addView(webView);
        rootLayout.addView(progressBar);
        rootLayout.addView(errorView);
        setContentView(rootLayout);

        // WebChromeClient with onShowFileChooser and progress handling
        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onProgressChanged(WebView view, int newProgress) {
                if (newProgress < 100) {
                    progressBar.setVisibility(View.VISIBLE);
                    progressBar.setProgress(newProgress);
                } else {
                    progressBar.setVisibility(View.GONE);
                }
            }

            @Override
            public boolean onShowFileChooser(WebView webView, ValueCallback<Uri[]> filePathCallback, FileChooserParams fileChooserParams) {
                if (mUploadMessage != null) {
                    mUploadMessage.onReceiveValue(null);
                    mUploadMessage = null;
                }
                mUploadMessage = filePathCallback;

                Intent intent = null;
                if (fileChooserParams != null) {
                    try {
                        intent = fileChooserParams.createIntent();
                    } catch (Exception ignored) {}
                }
                if (intent == null) {
                    intent = new Intent(Intent.ACTION_GET_CONTENT);
                    intent.addCategory(Intent.CATEGORY_OPENABLE);
                    intent.setType("image/*");
                }

                try {
                    Intent chooser = Intent.createChooser(intent, "Select Book Photo");
                    startActivityForResult(chooser, FILE_CHOOSER_RESULT_CODE);
                    return true;
                } catch (Exception e) {
                    if (mUploadMessage != null) {
                        mUploadMessage.onReceiveValue(null);
                        mUploadMessage = null;
                    }
                    Toast.makeText(MainActivity.this, "Cannot open photo chooser: " + e.getMessage(), Toast.LENGTH_SHORT).show();
                    return false;
                }
            }
        });

        // WebViewClient with offline handling and OAuth link handling
        webView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                String url = request.getUrl().toString();
                if (url.startsWith("https://verboseai.vercel.app") ||
                    url.startsWith("https://accounts.google.com") ||
                    url.startsWith("https://oauth2.googleapis.com") ||
                    url.startsWith("https://ssl.gstatic.com") ||
                    url.startsWith("https://apis.google.com") ||
                    url.startsWith("http://127.0.0.1:8080") ||
                    url.startsWith("http://localhost:8080")) {
                    return false;
                }
                try {
                    Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
                    startActivity(intent);
                } catch (Exception ignored) {}
                return true;
            }

            @Override
            public void onPageStarted(WebView view, String url, Bitmap favicon) {
                errorView.setVisibility(View.GONE);
                progressBar.setVisibility(View.VISIBLE);
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                progressBar.setVisibility(View.GONE);
            }

            @Override
            public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
                if (request.isForMainFrame()) {
                    progressBar.setVisibility(View.GONE);
                    errorView.setVisibility(View.VISIBLE);
                }
            }
        });

        if (savedInstanceState != null) {
            webView.restoreState(savedInstanceState);
        } else {
            webView.loadUrl(APP_URL);
        }
    }

    private LinearLayout createErrorView() {
        LinearLayout layout = new LinearLayout(this);
        layout.setOrientation(LinearLayout.VERTICAL);
        layout.setGravity(Gravity.CENTER);
        layout.setBackgroundColor(0xFF0C0D11);
        layout.setLayoutParams(new FrameLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            ViewGroup.LayoutParams.MATCH_PARENT
        ));
        int pad = dpToPx(24);
        layout.setPadding(pad, pad, pad, pad);

        TextView title = new TextView(this);
        title.setText("Connection Unavailable");
        title.setTextColor(0xFFFFFFFF);
        title.setTextSize(20);
        title.setGravity(Gravity.CENTER);
        title.setPadding(0, 0, 0, dpToPx(8));
        layout.addView(title);

        TextView subtitle = new TextView(this);
        subtitle.setText("Verbose AI requires an active internet connection to stream grounded literary intelligence.");
        subtitle.setTextColor(0xFF888899);
        subtitle.setTextSize(14);
        subtitle.setGravity(Gravity.CENTER);
        subtitle.setPadding(0, 0, 0, dpToPx(24));
        layout.addView(subtitle);

        Button retryBtn = new Button(this);
        retryBtn.setText("Retry Connection");
        retryBtn.setTextColor(0xFFFFFFFF);
        retryBtn.setBackgroundColor(0xFF1E222D);
        retryBtn.setPadding(dpToPx(24), dpToPx(12), dpToPx(24), dpToPx(12));
        retryBtn.setOnClickListener(v -> {
            errorView.setVisibility(View.GONE);
            webView.reload();
        });
        layout.addView(retryBtn);

        return layout;
    }

    private int dpToPx(int dp) {
        float density = getResources().getDisplayMetrics().density;
        return Math.round(dp * density);
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        if (requestCode == FILE_CHOOSER_RESULT_CODE) {
            if (mUploadMessage == null) return;
            Uri[] results = null;
            if (resultCode == Activity.RESULT_OK && data != null) {
                String dataString = data.getDataString();
                ClipData clipData = data.getClipData();
                if (clipData != null) {
                    results = new Uri[clipData.getItemCount()];
                    for (int i = 0; i < clipData.getItemCount(); i++) {
                        results[i] = clipData.getItemAt(i).getUri();
                    }
                } else if (dataString != null) {
                    results = new Uri[]{Uri.parse(dataString)};
                } else if (data.getData() != null) {
                    results = new Uri[]{data.getData()};
                }
            }
            mUploadMessage.onReceiveValue(results);
            mUploadMessage = null;
            return;
        }
        super.onActivityResult(requestCode, resultCode, data);
    }

    @Override
    public void onBackPressed() {
        if (errorView.getVisibility() == View.VISIBLE) {
            errorView.setVisibility(View.GONE);
            webView.reload();
            return;
        }
        if (webView.canGoBack()) {
            webView.goBack();
        } else {
            super.onBackPressed();
        }
    }

    @Override
    protected void onSaveInstanceState(Bundle outState) {
        super.onSaveInstanceState(outState);
        webView.saveState(outState);
    }

    public static class VerboseNativeBridge {
        private final Context context;

        public VerboseNativeBridge(Context context) {
            this.context = context;
        }

        @JavascriptInterface
        public boolean isNativeApp() {
            return true;
        }

        @JavascriptInterface
        public void showToast(final String message) {
            if (context instanceof Activity) {
                ((Activity) context).runOnUiThread(() ->
                    Toast.makeText(context, message, Toast.LENGTH_SHORT).show()
                );
            }
        }

        @JavascriptInterface
        public void vibrate(long ms) {
            try {
                Vibrator v = (Vibrator) context.getSystemService(Context.VIBRATOR_SERVICE);
                if (v != null && v.hasVibrator()) {
                    v.vibrate(ms);
                }
            } catch (Exception ignored) {}
        }
    }
}
