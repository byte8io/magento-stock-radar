import Link from '@docusaurus/Link';
import Layout from '@theme/Layout';
import styles from './index.module.css';

export default function Pricing(): React.ReactElement {
  return (
    <Layout
      title="Pricing — Byte8 Stock Radar"
      description="Free forever. The PlentyONE bridge is the paid upsell for DACH stores running PlentyONE — €199/year."
    >
      <main>
        <section className={styles.heroSection}>
          <div className={styles.heroContent}>
            <span className={styles.eyebrow}>Pricing</span>
            <h1 className={styles.heroTitle}>
              Free. <span className={styles.heroTitleAccent}>Forever.</span>
            </h1>
            <p className={styles.heroSubtitle}>
              Byte8 Stock Radar is MIT-licensed and free on GitHub + Composer +
              the upcoming Magento Marketplace listing. No expiring trial, no
              feature gating, no upsell tricks. The paid product is the{' '}
              <Link to="/docs/advanced/plenty-bridge">PlentyONE bridge</Link> —
              for DACH stores running PlentyONE who want live ERP inbound data
              in the demand heatmap and email.
            </p>
            <div className={styles.heroCtas}>
              <Link className="button button--primary button--lg" to="/docs/getting-started/quick-start">
                Install Stock Radar
              </Link>
              <Link className="button button--secondary button--lg" to="/docs/advanced/plenty-bridge">
                See the Plenty bridge
              </Link>
            </div>

            <div className={styles.statsRow}>
              <div className={styles.stat}>
                <span className={styles.statValue}>€0</span>
                <span className={styles.statLabel}>Stock Radar — free, MIT</span>
              </div>
              <div className={styles.stat}>
                <span className={styles.statValue}>€0</span>
                <span className={styles.statLabel}>Hyvä companion — free, MIT</span>
              </div>
              <div className={styles.stat}>
                <span className={styles.statValue}>€199</span>
                <span className={styles.statLabel}>Plenty bridge / year</span>
              </div>
              <div className={styles.stat}>
                <span className={styles.statValue}>€499</span>
                <span className={styles.statLabel}>Plenty bridge multi-store / year</span>
              </div>
            </div>
          </div>
        </section>

        <section className={styles.section}>
          <header className={styles.sectionHeader}>
            <span className={styles.sectionEyebrow}>Bundling</span>
            <p className={styles.sectionLead}>
              The Plenty bridge is included free in three scenarios — same logic as the rest of the Byte8 paid catalogue.
            </p>
          </header>

          <ul style={{ lineHeight: 1.8, color: 'var(--ifm-color-emphasis-700)' }}>
            <li><strong>Pro Service Support Plan subscriber</strong> — Plenty bridge Single tier included for the duration of the plan.</li>
            <li><strong>Multi-module SaaS suite ≥ €1,000/year</strong> — Plenty bridge Single tier included.</li>
            <li><strong>Custom Magento + PlentyONE project ≥ €15,000</strong> — Plenty bridge Single tier included for year 1.</li>
          </ul>
          <p style={{ marginTop: '1rem', fontSize: '0.9rem', color: 'var(--ifm-color-emphasis-600)' }}>
            See the cross-module commercial map in the <Link to="https://github.com/byte8io/magento-stock-radar/blob/main/packages/docs/stock-radar/PRODUCT_CONCEPT.md">PRODUCT_CONCEPT</Link> doc.
          </p>
        </section>
      </main>
    </Layout>
  );
}
