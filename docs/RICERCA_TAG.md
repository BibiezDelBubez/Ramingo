# Come si usano i tag: cosa fanno i siti migliori e cosa ne ricava Ramingo

Ricerca del 15/10/2026. Esempi da e-commerce (Etsy, Shopify, eBay, Baymard), piattaforme di contenuti
(YouTube, Steam, Stack Overflow, Archive of Our Own) e siti video per adulti, studiati qui solo per
navigazione e ricerca.

## In breve

- **I tag servono a spostarsi di lato**, non a sfogliare dall'alto: si arriva su un report, si clicca un suo tag,
  si vedono gli altri simili. Per sfogliare dall'alto si usano le categorie.
- **Quasi nessuno «cerca un tag».** Le persone scrivono parole; è il sistema a riconoscere che una parola è un tag
  e a proporlo come scelta nei suggerimenti.
- **Il problema numero uno dei tag sono le varianti** («lavaggi», «lavaggio», «lavaggi cisterne»).
  Tutti i siti seri lo risolvono con un vocabolario curato: sinonimi che portano a un tag principale.
- **Quando molti tag sono dello stesso tipo, diventano un filtro a sé** (es. «Cliente»), non restano tag.

## 1. Come vengono usati i tag

| Pratica | Chi lo fa | Dettagli |
|---|---|---|
| **Tag visibili sull'elemento e cliccabili** | YouTube, Steam, Confluence, siti video | YouTube mostra i primi 3 hashtag sopra il titolo; il clic apre i risultati con quell'hashtag |
| **Pochi tag, i più importanti per primi** | Steam, YouTube | Steam: si possono aggiungere molti tag ma solo i primi 20 sono visibili e usati; i primi 5 devono descrivere il gioco. YouTube ignora tutti gli hashtag di un video se sono più di 15 |
| **Vocabolario approvato, raggruppato per tipo** | Steam | Centinaia di tag approvati, divisi in famiglie (Generi, Temi e atmosfere, Caratteristiche…) |
| **Sinonimi verso un tag principale** | Stack Overflow, AO3 | Stack Overflow: [js] e [java-script] diventano automaticamente [javascript]. AO3: il tag «sinonimo» reindirizza a quello principale e non compare nei suggerimenti |
| **Solo i tag principali sono filtrabili** | AO3 | I tag liberi si vedono ma non si possono usare come filtro finché qualcuno non li collega a un tag principale |
| **Gerarchia leggera** | AO3 | «Metatag» che raggruppano tag più specifici; filtrando sul metatag si prendono anche quelli sotto |
| **Tag come parole chiave di ricerca** | Etsy | I 13 tag di un prodotto servono soprattutto a farlo trovare dalla ricerca |
| **Tag per «altri simili»** | Steam | I suggerimenti «Altri come questo» usano i tag in comune; i tag rari pesano più di quelli comuni |
| **Le famiglie regolari diventano filtri separati** | Shopify | Per filtrare si preferiscono campi dedicati (un filtro per attributo) ai tag, che finiscono in «un unico grande mucchio» |

## 2. Come gli utenti cercano tramite i tag

1. **Clic su un tag di un elemento.** È il gesto più frequente: nessuno deve sapere in anticipo che il tag esiste.
2. **Suggerimenti mentre si scrive.** I siti migliori mostrano nel menu dei suggerimenti anche entità
   (canali, categorie, tag) oltre alle parole. Baymard raccomanda:
   - al massimo 10 suggerimenti su computer (4–8 su telefono);
   - le scelte di **ambito** («caffè *in Alimentari*») con uno stile diverso dal testo cercato;
   - solo il 32% dei 50 maggiori e-commerce USA lo fa, quindi è un vantaggio facile.
3. **Pannello filtri con l'elenco dei tag** accanto ai risultati (Steam lo mette a destra dei risultati di ricerca),
   con il numero di elementi e una casella per cercare nell'elenco.
4. **Più tag insieme.** I siti video permettono di combinare tag per restringere; viene giudicato più veloce che sfogliare pagina per pagina.
5. **Escludere un tag.** AO3 permette «includi» ed «escludi». Uno studio sugli utenti (Humboldt-Universität, 2025)
   trova che l'esclusione è molto usata e apprezzata, ma funziona solo se il vocabolario dei tag è curato.
6. **Pagina indice di categorie e tag.** I siti video hanno una pagina con circa 90 categorie a riquadri con immagine,
   più le ricerche di tendenza per chi non sa cosa cercare.
7. **Nuvole di tag: utili poco.** Uno studio (Sinclair e Cardew-Hall) trova che servono per esplorare un argomento
   in generale, mentre per un'informazione precisa le persone preferiscono la casella di ricerca; non bastano
   come unico modo di navigare.

8. **Paper: Helic, Trattner, Strohmaier, Andrews (TU Graz, 2011), «Are tag clouds useful for navigation?».**
   Simulano la navigazione «tag → elenco degli elementi con quel tag → elemento → altro tag…» su tre sistemi reali
   (Austria-Forum, BibSonomy, CiteULike). Risultati, testuali:
   - **R1** – L'utilità dei tag per navigare dipende dalla maturità del sistema: nel sistema giovane (Austria-Forum)
     solo il 77% degli elementi è raggiungibile saltando di tag in tag, contro il 98% dei sistemi maturi.
   - **R2** – Un sistema di tag maturo è in teoria navigabile in pochi passi (diametro effettivo sotto 7).
   - **R3** – **Mostrare pochi tag per elemento (5, 10 o più) non peggiora la navigazione.**
   - **R4** – **Limitare l'elenco degli elementi di un tag** (paginazione, ordinata dal più recente, uguale ovunque)
     **spezza la rete**: la parte raggiungibile crolla al 27–60%. È il problema principale.
   - **R5** – Elenchi di 20 o più elementi per tag conservano il significato del tag (perdita ≈1%); 5–10 elementi no.
   - Rimedio proposto: quando un tag ha troppi elementi, **organizzarli in una gerarchia** invece di troncarli,
     cercando un equilibrio tra «facile da navigare» e «sensato per significato».
   - Dalla letteratura citata: i tag troppo generici, che finiscono su troppi elementi, perdono utilità
     (Chi e Mytkowicz 2008); le nuvole di tag servono per esplorare, poco per cercare qualcosa di preciso
     (Sinclair e Cardew-Hall 2008).

   **Cosa significa per Ramingo:**
   - mostrare pochi tag per report va bene (R3): la regola «massimo 5» regge;
   - **mai troncare o paginare l'elenco dei report di un tag o di un filtro** (R4): con un centinaio di report si mostrano tutti;
   - con pochi tag e pochi report i tag non bastano come navigazione (R1): servono categorie e ricerca, i tag vengono dopo;
   - se un tag raccoglie molti report, l'elenco va **raggruppato per categoria** invece di essere tagliato (rimedio del paper);
   - i tag devono essere specifici: un tag messo su metà dei report è inutile.

   **Nuvola dei tag più frequenti (idea per la pagina iniziale).** Il paper studia i tag mostrati su ogni elemento,
   non una nuvola globale; ma i tag più frequenti sono proprio gli «snodi» che, secondo R2–R4, rendono la rete
   navigabile, a patto che cliccandoli si vedano tutti gli elementi. Quindi una nuvola dei tag più frequenti è
   un buon punto d'ingresso per esplorare, se:
   - cliccando un tag si vedono **tutti** i report con quel tag;
   - compare solo quando i tag sono abbastanza diffusi (R1), per esempio almeno 8 tag e almeno metà dei report con un tag;
   - esclude i tag presenti su quasi tutti i report (non specifici: sono categorie mancate);
   - resta un aiuto a esplorare, non il modo principale di cercare (Sinclair e Cardew-Hall).
   I «più cercati» invece richiedono di registrare le ricerche fatte in Ramingo (oggi non avviene).

## 3. Categorie e tag nei siti video

- **Categorie:** poche decine, curate dal sito, in una pagina di riquadri visivi: servono a sfogliare.
- **Tag:** molti, su ogni video, cliccabili, usati per «altri come questo» e per combinare filtri.
- **Ricerca:** in grande in alto, con suggerimenti dopo pochi caratteri e ricerche di tendenza.
- **Nessun «workspace»:** l'organizzazione interna di chi carica non interessa a chi guarda.

## 4. Cosa ne ricava Ramingo

Già fatto:
- tag cliccabili che portano all'elenco filtrato;
- tag già usati proposti mentre li si scrive (contro le varianti);
- la ricerca trova i report anche tramite i loro tag.

Da fare, in ordine di valore:
1. **Suggerimenti di ricerca con tag e categorie** come voci distinte («#lavaggi · 5 report», «Categoria: Contabilità»),
   stile diverso dalle parole, al massimo 8 voci.
2. **Filtro Tag a caselle** (più tag insieme = report che li hanno tutti), ordinati per uso, con casella di ricerca.
3. **Unire tag** in amministrazione: «lavaggio» → «lavaggi». Diventa utile appena i tag sono più di una ventina.
4. **Al massimo 5 tag per report**, il primo è il più importante.
5. **Pagina del tag con «tag correlati»** (quelli che compaiono più spesso insieme).
6. **Escludere un tag** («senza #test»): solo quando i tag saranno curati.
7. **Famiglie di tag → filtri dedicati.** Se nascono molti tag dello stesso tipo (es. clienti come Gemina e KeyPartner,
   o la frequenza «giornaliero/mensile»), conviene un filtro apposito invece dei tag.

Da non fare: la nuvola di tag come navigazione principale.

Per dopo: «più consultati» e «di tendenza» richiedono i dati di utilizzo dei report, che Power BI espone
nel registro attività (API amministrative).

## Fonti

- [Steamworks – Steam Tags](https://partner.steamgames.com/doc/store/tags?l=english)
- [Stack Overflow Blog – Tag folksonomy and tag synonyms](https://stackoverflow.blog/2010/08/01/tag-folksonomy-and-tag-synonyms/)
- [Fanlore – Tag wrangling su AO3 (canonical, synned, metatag)](https://www.fanlore.org/wiki/Synned)
- [Humboldt-Universität – Studio sul filtro «escludi tag» di AO3](https://edoc.hu-berlin.de/18452/32169)
- [Tubefilter – Hashtag cliccabili sopra i titoli di YouTube](https://tubefilter.com/2018/07/18/youtube-hashtags-above-titles/)
- [Outfy – Come Etsy usa i tag](https://www.outfy.com/blog/etsy-tags/)
- [Shopify Community – Tag o metafield per i filtri](https://community.shopify.com/t/best-practice-for-managing-900-light-bulb-products-with-multiple-attributes-tags-vs-metafields-faceted-search-breadcrumbs/562464)
- [Baymard – Search scope](https://baymard.com/blog/search-scope)
- [Baymard – Autocomplete design](https://baymard.com/research-articles/autocomplete-design)
- [eBay Playbook – Filtering patterns](https://playbook.ebay.com/design-system/patterns/filtering-patterns)
- [Medium – UX Lessons from Pornhub](https://medium.com/@designthad/ux-lessons-from-pornhub-insights-from-an-nsfw-website-a0825c43a8e6)
- [AIGA Eye on Design – The UX of porn tube sites](https://eyeondesign.aiga.org/the-ux-of-porn-tubes-are-designed-for-the-ultimate-money-shot/)
- [Medium – Porn websites are the kings of UX](https://lorenzodoremi.medium.com/porn-websites-are-the-kings-of-ux-574bd0ed2978)
- [Helic et al. 2011 – Are tag clouds useful for navigation? A network-theoretic analysis (DOI 10.1504/IJSCCPS.2011.043603)](https://doi.org/10.1504/IJSCCPS.2011.043603)
- [SAGE – The folksonomy tag cloud: when is it useful?](https://journals.sagepub.com/doi/10.1177/0165551506078083)
