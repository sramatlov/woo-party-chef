# Woo Party Chef 1.3.0 terugrollen

## Alleen de automatische aanpassing uitschakelen

1. Open de betreffende pagina in Elementor en selecteer de Shortcode-widget.
2. Voeg `layout="classic"` toe aan de bestaande shortcode. Bijvoorbeeld:

   ```text
   [woo_party_chef layout="classic"]
   ```

   Behoud eventuele andere instellingen in de shortcode.
3. Sla de pagina op en gebruik in WP Rocket **Purge this URL** voor deze pagina.

De planner gebruikt nu de ruime indeling van versie 1.2.2. De afgeronde kortingen
en de aangepaste opmaak van het onderste vlak blijven behouden. Dit is per pagina
instelbaar. Verwijder `layout="classic"` om de automatische aanpassing weer aan
te zetten; sla daarna op en leeg opnieuw de paginacache.

## De hele plugin terugzetten naar 1.2.2

1. Gebruik het bewaarde bestand `woo-party-chef-v1.2.2-install.zip`.
2. Upload het via **Plugins > Nieuwe plugin > Plugin uploaden** in WordPress.
3. Kies **Replace current with uploaded / Huidige vervangen door geüploade**.
4. Leeg via WP Rocket de cache van de betreffende campagnepagina.

Versie 1.3.0 verandert geen opgeslagen productgegevens en voert geen
databasemigratie uit. Versie 1.2.2 bevat de eerdere kortingsafronding en de
bijgewerkte opmaak van ‘In elke Chef’s Dinner Party’.
