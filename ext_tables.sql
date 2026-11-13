# Columns TYPO3 cannot derive from TCA; all others come from TCA.
CREATE TABLE tx_provenancefacts_fact (
    content_uid int(11) unsigned DEFAULT '0' NOT NULL,
    KEY content (content_uid),
    KEY fact (fact_id)
);
