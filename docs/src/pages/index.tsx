import Link from '@docusaurus/Link';
import Layout from '@theme/Layout';
import styles from './index.module.css';

interface FeatureCardProps {
  href: string;
  icon: string;
  title: string;
  body: string;
  cta: string;
}

function FeatureCard({ href, icon, title, body, cta }: FeatureCardProps) {
  return (
    <Link to={href} className={styles.featureCard}>
      <span className={styles.featureIcon} aria-hidden>{icon}</span>
      <h3 className={styles.featureTitle}>{title}</h3>
      <p className={styles.featureBody}>{body}</p>
      <span className={styles.featureFooter}>{cta} ↘</span>
    </Link>
  );
}

export default function Home(): React.ReactElement {
  return (
    <Layout
      title="Byte8 Stock Radar — Magento 2 back-in-stock notifications"
      description="Throttled back-in-stock notifications for Magento 2 with per-variant subscriptions, GraphQL, and a real demand heatmap for merchandisers. Free, MIT-licensed."
    >
      <main>
        {/* Hero */}
        <section className={styles.heroSection}>
          <div className={styles.heroContent}>
            <span className={styles.eyebrow}>Magento 2 · Free · MIT</span>
            <h1 className={styles.heroTitle}>
              Back-in-stock that{' '}
              <span className={styles.heroTitleAccent}>doesn't blast everyone</span>{' '}
              at 09:00.
            </h1>
            <p className={styles.heroSubtitle}>
              Throttled batched notifications, per-variant subscriptions on configurables,
              and a demand heatmap that tells your merchandiser exactly what to reorder.
              Hyvä-clean, headless-ready, GDPR-first.
            </p>
            <div className={styles.heroCtas}>
              <Link className="button button--primary button--lg" to="/docs/getting-started/quick-start">
                Get started in 5 minutes
              </Link>
              <Link className="button button--secondary button--lg" to="/docs/intro">
                Read the docs
              </Link>
            </div>

            <div className={styles.statsRow}>
              <div className={styles.stat}>
                <span className={styles.statValue}>30 min</span>
                <span className={styles.statLabel}>Default throttle window</span>
              </div>
              <div className={styles.stat}>
                <span className={styles.statValue}>O(1)</span>
                <span className={styles.statLabel}>GDPR delete by email_hash</span>
              </div>
              <div className={styles.stat}>
                <span className={styles.statValue}>200/min</span>
                <span className={styles.statLabel}>Cron dispatch batch limit</span>
              </div>
              <div className={styles.stat}>
                <span className={styles.statValue}>MIT</span>
                <span className={styles.statLabel}>License — free forever</span>
              </div>
            </div>
          </div>
        </section>

        {/* Core capabilities */}
        <section className={styles.section}>
          <header className={styles.sectionHeader}>
            <span className={styles.sectionEyebrow}>Core</span>
            <p className={styles.sectionLead}>
              Throttled, idempotent, headless-ready. Built for stores that move real volume.
            </p>
          </header>

          <div className={styles.cardGrid}>
            <FeatureCard
              href="/docs/configuration/dispatch"
              icon="⏱️"
              title="Throttled batches"
              body="Each subscription gets a randomly-staggered scheduled_at within the configurable window. A 800-subscriber restock spreads naturally — no inventory crash, no spam-filter pattern-match, no mail-server rate limit."
              cta="Dispatch settings"
            />
            <FeatureCard
              href="/docs/getting-started/first-subscription"
              icon="🎨"
              title="Per-variant on configurables"
              body="Subscribe to 'Red, M' specifically — not the parent SKU. Variant resolution is server-emitted into the form so the JS bridge stays simple."
              cta="First subscription"
            />
            <FeatureCard
              href="/docs/admin/demand-heatmap"
              icon="📊"
              title="Demand heatmap"
              body="Sortable admin grid ranking products by pending subscriber count. The reorder report your merchandiser actually wants — first subscribed, latest subscribed, store-aware grouping."
              cta="Demand heatmap"
            />
          </div>
        </section>

        {/* Front-end */}
        <section className={styles.section}>
          <header className={styles.sectionHeader}>
            <span className={styles.sectionEyebrow}>Front-end</span>
            <p className={styles.sectionLead}>
              "Notify me" buttons that fit every Magento storefront family — without forcing one.
            </p>
          </header>

          <div className={styles.cardGrid}>
            <FeatureCard
              href="/docs/frontend/luma"
              icon="🧱"
              title="Luma drop-in"
              body="Layout XML places the form into the standard product.info.stock.sku container. Plain RequireJS, no theme contortion. Customer account 'My Stock Notifications' page included."
              cta="Luma integration"
            />
            <FeatureCard
              href="/docs/frontend/hyva"
              icon="⚡"
              title="Hyvä companion"
              body="Alpine + Tailwind variant — no jQuery, no RequireJS. Reads variant SKU updates via MutationObserver on selected_configurable_option for clean reactivity."
              cta="Hyvä module"
            />
            <FeatureCard
              href="/docs/frontend/velafront"
              icon="▲"
              title="VelaFront / headless"
              body="Full GraphQL parity — subscribe, unsubscribe, list mutations and queries. Plug straight into Next.js, Hydrogen, or any headless storefront."
              cta="Headless / GraphQL"
            />
          </div>
        </section>

        {/* Admin / GDPR */}
        <section className={styles.section}>
          <header className={styles.sectionHeader}>
            <span className={styles.sectionEyebrow}>Admin & compliance</span>
            <p className={styles.sectionLead}>
              The admin tools merchandisers actually use, with privacy treated as a feature.
            </p>
          </header>

          <div className={styles.cardGrid}>
            <FeatureCard
              href="/docs/admin/subscription-grid"
              icon="📒"
              title="Subscription grid"
              body="Filter by status, customer, store, date. Status enum (pending / notified / cancelled / bounced). Store-aware with per-store throttle windows."
              cta="Subscription admin"
            />
            <FeatureCard
              href="/docs/advanced/gdpr"
              icon="🛡️"
              title="GDPR-first"
              body="Guest emails stored as SHA-256 hash next to plaintext. Data subject deletion is O(1) by email_hash. Signed unsubscribe tokens — no token-validity leak in the unsubscribe response."
              cta="GDPR & privacy"
            />
            <FeatureCard
              href="/docs/advanced/plenty-bridge"
              icon="🔌"
              title="PlentyONE bridge (paid)"
              body="DACH stores running PlentyONE: extend the demand heatmap with live ERP data — physical, net, and inbound (PO) quantities. Enriched email template with PO ETAs. €199/yr."
              cta="Plenty bridge"
            />
          </div>
        </section>

        {/* CTA band */}
        <section className={styles.ctaBand}>
          <h2 className={styles.ctaTitle}>Five minutes to running.</h2>
          <p className={styles.ctaSubtitle}>
            Composer install, one config flag, and the cron does the rest. No external account, no API key.
          </p>
          <div className={styles.heroCtas}>
            <Link className="button button--primary button--lg" to="/docs/getting-started/quick-start">
              Quick start
            </Link>
            <Link className="button button--secondary button--lg" to="https://github.com/byte8io/magento-stock-radar">
              View on GitHub
            </Link>
          </div>
        </section>
      </main>
    </Layout>
  );
}
