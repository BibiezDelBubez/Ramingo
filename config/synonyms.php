<?php
/**
 * Vocabolario aziendale per la ricerca "intelligente".
 * Ogni gruppo = parole equivalenti (italiano, inglese, gergo interno).
 * Modificabile liberamente: è il modo più efficace per migliorare la ricerca.
 * Il PRIMO termine del gruppo è quello mostrato come concetto.
 */
return [
    'groups' => [
        ['vendite', 'vendita', 'venduto', 'sales', 'sell', 'sellout', 'sell-in', 'fatturato', 'ricavi', 'revenue'], // «ordini» tolto: nei trasporti un ordine non è una vendita
        ['regione', 'regioni', 'region', 'regional', 'area', 'zona', 'territorio', 'geografia', 'provincia', 'nazione', 'country', 'mappa'],
        ['cliente', 'clienti', 'customer', 'customers', 'account', 'anagrafica'],
        ['prodotto', 'prodotti', 'product', 'articolo', 'articoli', 'sku', 'item', 'catalogo'],
        ['margine', 'marginalità', 'margin', 'profitto', 'profit', 'redditività', 'ebitda'],
        ['costo', 'costi', 'cost', 'spese', 'spesa', 'expense', 'budget'],
        ['magazzino', 'scorte', 'giacenze', 'inventory', 'stock', 'warehouse'],
        ['personale', 'dipendenti', 'hr', 'risorse umane', 'organico', 'headcount', 'turnover', 'cessati', 'employee', 'assenze', 'presenze'],
        ['acquisti', 'procurement', 'purchasing', 'fornitori', 'supplier', 'vendor'],
        ['produzione', 'production', 'manufacturing', 'stabilimento', 'plant', 'oee', 'scarti'],
        ['logistica', 'spedizioni', 'shipping', 'consegne', 'delivery', 'trasporti'],
        ['finanza', 'finance', 'contabilità', 'bilancio', 'cassa', 'cash', 'crediti', 'debiti'],
        ['agente', 'agenti', 'venditore', 'venditori', 'commerciale', 'sales rep', 'rete vendita'],
        ['andamento', 'trend', 'storico', 'mensile', 'annuale', 'yoy', 'confronto', 'variazione'],
        ['obiettivo', 'obiettivi', 'target', 'goal', 'budget vs actual', 'forecast', 'previsione'],
    ],

    // Parole ignorate nelle domande in linguaggio naturale
    'stopwords' => [
        'voglio', 'vorrei', 'vedere', 'mostra', 'mostrami', 'cerco', 'trova', 'trovami', 'dammi', 'serve', 'mi', 'ci',
        'il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'uno', 'una', 'di', 'a', 'da', 'in', 'con', 'su', 'per', 'tra', 'fra',
        'del', 'dello', 'della', 'dei', 'degli', 'delle', 'al', 'allo', 'alla', 'ai', 'agli', 'alle', 'nel', 'nella', 'nei', 'nelle',
        'e', 'o', 'ed', 'che', 'come', 'dove', 'quale', 'quali', 'quanto', 'quanti', 'report', 'dashboard', 'dati', 'tutti', 'tutte',
        'the', 'of', 'by', 'for', 'and', 'or', 'show', 'me', 'per',
    ],
];
