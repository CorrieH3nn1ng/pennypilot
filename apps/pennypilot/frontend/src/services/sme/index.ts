/**
 * SME Services Index
 *
 * Universal Factory for SME financial analysis:
 * - Format-blind statement parsing
 * - Auto income recognition with tax set-aside
 * - Intelligent expense categorization
 * - December summary builder
 */

export {
  SMEIncomeRecognizer,
  recognizeNucleusMiningIncome,
  type KnownClient,
  type IncomeMatch,
  type IncomeRecognitionResult,
} from './SMEIncomeRecognizer';

export {
  IntelligentCategorizer,
  quickCategorize,
  type SMEExpenseCategory,
  type CategorizationRule,
  type CategorizedTransaction,
  type CategorizationResult,
} from './IntelligentCategorizer';

export {
  DecemberSMESummaryBuilder,
  buildDecemberSummary,
  formatSummaryForDisplay,
  type DecemberSummary,
  type DecemberSummaryOptions,
} from './DecemberSMESummary';
