// Exposer uniquement les fonctionnalités utilisées par l'impression d'étiquettes.
// Importer directement le namespace complet de pdf-lib empêchait Vite d'élaguer
// les modules inutilisés du paquet.
export { PDFDocument, StandardFonts, rgb } from 'pdf-lib';
