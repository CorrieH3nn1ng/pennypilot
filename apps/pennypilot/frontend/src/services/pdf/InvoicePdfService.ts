/**
 * SARS 2025/26 Compliant Invoice PDF Generator
 *
 * Generates Tax Invoice PDFs with all required SARS fields:
 * - "TAX INVOICE" label (required for VAT invoices)
 * - Invoice serial number
 * - Seller's physical address
 * - Banking details
 * - VAT registration number (if applicable)
 */

import { jsPDF } from 'jspdf';
import type { Invoice, BusinessProfile } from '@/types';

// Deep Teal brand color
const DEEP_TEAL = { r: 0, g: 77, b: 64 }; // #004D40
const TEXT_DARK = { r: 55, g: 71, b: 79 };
const TEXT_GREY = { r: 120, g: 144, b: 156 };

interface GeneratorOptions {
  invoice: Invoice;
  profile: BusinessProfile;
}

export class InvoicePdfService {
  private doc: jsPDF;
  private pageWidth: number;
  private pageHeight: number;
  private margin: number;
  private currentY: number;

  constructor() {
    this.doc = new jsPDF('p', 'mm', 'a4');
    this.pageWidth = this.doc.internal.pageSize.getWidth();
    this.pageHeight = this.doc.internal.pageSize.getHeight();
    this.margin = 20;
    this.currentY = this.margin;
  }

  /**
   * Generate SARS 2025/26 compliant Tax Invoice PDF
   */
  generate({ invoice, profile }: GeneratorOptions): Blob {
    this.doc = new jsPDF('p', 'mm', 'a4');
    this.currentY = this.margin;

    // Header section
    this.renderHeader(profile, invoice);

    // Invoice details
    this.renderInvoiceDetails(invoice);

    // From/To section
    this.renderAddresses(profile, invoice);

    // Line items table
    this.renderLineItems(invoice);

    // Totals section
    this.renderTotals(invoice);

    // Banking details (SARS requirement)
    this.renderBankingDetails(profile);

    // Notes
    if (invoice.notes) {
      this.renderNotes(invoice.notes);
    }

    // Footer
    this.renderFooter(profile);

    return this.doc.output('blob');
  }

  /**
   * Generate and trigger download
   */
  download({ invoice, profile }: GeneratorOptions): void {
    const blob = this.generate({ invoice, profile });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${invoice.invoice_number}.pdf`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }

  // === Private Rendering Methods ===

  private renderHeader(profile: BusinessProfile, invoice: Invoice): void {
    const contentWidth = this.pageWidth - (this.margin * 2);

    // TAX INVOICE label (SARS requirement for VAT invoices)
    this.doc.setFillColor(DEEP_TEAL.r, DEEP_TEAL.g, DEEP_TEAL.b);
    this.doc.rect(0, 0, this.pageWidth, 12, 'F');

    const invoiceLabel = invoice.tax_rate > 0 ? 'TAX INVOICE' : 'INVOICE';
    this.doc.setTextColor(255, 255, 255);
    this.doc.setFontSize(14);
    this.doc.setFont('helvetica', 'bold');
    this.doc.text(invoiceLabel, this.pageWidth / 2, 8, { align: 'center' });

    this.currentY = 20;

    // Business name (left side)
    this.doc.setTextColor(DEEP_TEAL.r, DEEP_TEAL.g, DEEP_TEAL.b);
    this.doc.setFontSize(16);
    this.doc.setFont('helvetica', 'bold');
    const businessName = profile.trading_name || profile.business_name || 'Your Business';
    this.doc.text(businessName, this.margin, this.currentY);

    // Invoice number (right side)
    this.doc.setTextColor(TEXT_DARK.r, TEXT_DARK.g, TEXT_DARK.b);
    this.doc.setFontSize(12);
    this.doc.setFont('helvetica', 'bold');
    this.doc.text(invoice.invoice_number, this.pageWidth - this.margin, this.currentY, { align: 'right' });

    this.currentY += 8;

    // Registration details below business name
    this.doc.setFontSize(9);
    this.doc.setFont('helvetica', 'normal');
    this.doc.setTextColor(TEXT_GREY.r, TEXT_GREY.g, TEXT_GREY.b);

    const regDetails: string[] = [];
    if (profile.registration_number) {
      regDetails.push(`Reg: ${profile.registration_number}`);
    }
    if (profile.tax_number) {
      regDetails.push(`Tax: ${profile.tax_number}`);
    }
    if (profile.vat_number) {
      regDetails.push(`VAT: ${profile.vat_number}`);
    }

    if (regDetails.length > 0) {
      this.doc.text(regDetails.join('  |  '), this.margin, this.currentY);
      this.currentY += 5;
    }

    this.currentY += 5;
  }

  private renderInvoiceDetails(invoice: Invoice): void {
    const detailsY = this.currentY;
    const colWidth = 45;
    const startX = this.pageWidth - this.margin - (colWidth * 2);

    this.doc.setFontSize(9);
    this.doc.setFont('helvetica', 'normal');

    // Labels
    this.doc.setTextColor(TEXT_GREY.r, TEXT_GREY.g, TEXT_GREY.b);
    this.doc.text('Invoice Date:', startX, detailsY);
    this.doc.text('Due Date:', startX, detailsY + 5);
    if (invoice.paid_date) {
      this.doc.text('Paid Date:', startX, detailsY + 10);
    }
    this.doc.text('Status:', startX, detailsY + (invoice.paid_date ? 15 : 10));

    // Values
    this.doc.setTextColor(TEXT_DARK.r, TEXT_DARK.g, TEXT_DARK.b);
    this.doc.setFont('helvetica', 'bold');
    this.doc.text(this.formatDate(invoice.invoice_date), startX + colWidth, detailsY);
    this.doc.text(this.formatDate(invoice.due_date), startX + colWidth, detailsY + 5);
    if (invoice.paid_date) {
      this.doc.text(this.formatDate(invoice.paid_date), startX + colWidth, detailsY + 10);
    }

    // Status with color
    const statusY = detailsY + (invoice.paid_date ? 15 : 10);
    const statusColors: Record<string, { r: number; g: number; b: number }> = {
      draft: { r: 120, g: 120, b: 120 },
      sent: { r: 33, g: 150, b: 243 },
      paid: { r: 46, g: 125, b: 50 },
      overdue: { r: 198, g: 40, b: 40 },
      cancelled: { r: 120, g: 120, b: 120 },
    };
    const statusColor = statusColors[invoice.status] || statusColors.draft;
    this.doc.setTextColor(statusColor.r, statusColor.g, statusColor.b);
    this.doc.text(invoice.status.toUpperCase(), startX + colWidth, statusY);

    this.currentY = Math.max(this.currentY, statusY + 10);
  }

  private renderAddresses(profile: BusinessProfile, invoice: Invoice): void {
    const halfWidth = (this.pageWidth - (this.margin * 3)) / 2;

    // FROM section (left)
    this.doc.setFillColor(245, 245, 245);
    this.doc.roundedRect(this.margin, this.currentY, halfWidth, 35, 2, 2, 'F');

    this.doc.setFontSize(8);
    this.doc.setFont('helvetica', 'bold');
    this.doc.setTextColor(TEXT_GREY.r, TEXT_GREY.g, TEXT_GREY.b);
    this.doc.text('FROM', this.margin + 4, this.currentY + 5);

    this.doc.setFont('helvetica', 'normal');
    this.doc.setTextColor(TEXT_DARK.r, TEXT_DARK.g, TEXT_DARK.b);
    this.doc.setFontSize(9);

    let fromY = this.currentY + 10;
    const fromName = profile.trading_name || profile.business_name || 'Your Business';
    this.doc.setFont('helvetica', 'bold');
    this.doc.text(fromName, this.margin + 4, fromY);
    this.doc.setFont('helvetica', 'normal');
    fromY += 4;

    if (profile.email) {
      this.doc.text(profile.email, this.margin + 4, fromY);
      fromY += 4;
    }
    if (profile.phone) {
      this.doc.text(profile.phone, this.margin + 4, fromY);
      fromY += 4;
    }
    // Physical address (SARS requirement)
    if (profile.address) {
      const addressLines = this.wrapText(profile.address, halfWidth - 8);
      addressLines.slice(0, 3).forEach(line => {
        this.doc.text(line, this.margin + 4, fromY);
        fromY += 4;
      });
    }

    // TO section (right)
    const toX = this.margin + halfWidth + this.margin;
    this.doc.setFillColor(245, 245, 245);
    this.doc.roundedRect(toX, this.currentY, halfWidth, 35, 2, 2, 'F');

    this.doc.setFontSize(8);
    this.doc.setFont('helvetica', 'bold');
    this.doc.setTextColor(TEXT_GREY.r, TEXT_GREY.g, TEXT_GREY.b);
    this.doc.text('TO', toX + 4, this.currentY + 5);

    this.doc.setFont('helvetica', 'normal');
    this.doc.setTextColor(TEXT_DARK.r, TEXT_DARK.g, TEXT_DARK.b);
    this.doc.setFontSize(9);

    let toY = this.currentY + 10;
    if (invoice.client) {
      this.doc.setFont('helvetica', 'bold');
      this.doc.text(invoice.client.name, toX + 4, toY);
      this.doc.setFont('helvetica', 'normal');
      toY += 4;

      if (invoice.client.contact_person) {
        this.doc.text(invoice.client.contact_person, toX + 4, toY);
        toY += 4;
      }
      if (invoice.client.email) {
        this.doc.text(invoice.client.email, toX + 4, toY);
        toY += 4;
      }
      if (invoice.client.address) {
        const clientAddressLines = this.wrapText(invoice.client.address, halfWidth - 8);
        clientAddressLines.slice(0, 2).forEach(line => {
          this.doc.text(line, toX + 4, toY);
          toY += 4;
        });
      }
    }

    this.currentY += 42;
  }

  private renderLineItems(invoice: Invoice): void {
    const tableWidth = this.pageWidth - (this.margin * 2);
    const colWidths = {
      description: tableWidth * 0.45,
      qty: tableWidth * 0.1,
      unit: tableWidth * 0.1,
      rate: tableWidth * 0.15,
      amount: tableWidth * 0.2,
    };

    // Table header
    this.doc.setFillColor(DEEP_TEAL.r, DEEP_TEAL.g, DEEP_TEAL.b);
    this.doc.rect(this.margin, this.currentY, tableWidth, 8, 'F');

    this.doc.setTextColor(255, 255, 255);
    this.doc.setFontSize(8);
    this.doc.setFont('helvetica', 'bold');

    let headerX = this.margin + 3;
    this.doc.text('Description', headerX, this.currentY + 5.5);
    headerX += colWidths.description;
    this.doc.text('Qty', headerX, this.currentY + 5.5, { align: 'center' });
    headerX += colWidths.qty;
    this.doc.text('Unit', headerX, this.currentY + 5.5, { align: 'center' });
    headerX += colWidths.unit;
    this.doc.text('Rate', headerX + colWidths.rate - 3, this.currentY + 5.5, { align: 'right' });
    headerX += colWidths.rate;
    this.doc.text('Amount', headerX + colWidths.amount - 3, this.currentY + 5.5, { align: 'right' });

    this.currentY += 10;

    // Table rows
    this.doc.setTextColor(TEXT_DARK.r, TEXT_DARK.g, TEXT_DARK.b);
    this.doc.setFont('helvetica', 'normal');

    invoice.items.forEach((item, index) => {
      // Zebra striping
      if (index % 2 === 0) {
        this.doc.setFillColor(250, 250, 250);
        this.doc.rect(this.margin, this.currentY - 1, tableWidth, 7, 'F');
      }

      let rowX = this.margin + 3;

      // Description (may wrap)
      const descLines = this.wrapText(item.description, colWidths.description - 6);
      this.doc.text(descLines[0], rowX, this.currentY + 4);

      rowX += colWidths.description;
      this.doc.text(item.quantity.toString(), rowX, this.currentY + 4, { align: 'center' });

      rowX += colWidths.qty;
      this.doc.text(item.unit || '-', rowX, this.currentY + 4, { align: 'center' });

      rowX += colWidths.unit;
      this.doc.text(this.formatMoney(item.unit_price), rowX + colWidths.rate - 3, this.currentY + 4, { align: 'right' });

      rowX += colWidths.rate;
      this.doc.text(this.formatMoney(item.amount), rowX + colWidths.amount - 3, this.currentY + 4, { align: 'right' });

      this.currentY += 7;

      // Additional description lines
      if (descLines.length > 1) {
        descLines.slice(1).forEach(line => {
          this.doc.text(line, this.margin + 3, this.currentY + 2);
          this.currentY += 5;
        });
      }
    });

    // Bottom border
    this.doc.setDrawColor(DEEP_TEAL.r, DEEP_TEAL.g, DEEP_TEAL.b);
    this.doc.setLineWidth(0.5);
    this.doc.line(this.margin, this.currentY, this.margin + tableWidth, this.currentY);

    this.currentY += 8;
  }

  private renderTotals(invoice: Invoice): void {
    const totalsWidth = 70;
    const totalsX = this.pageWidth - this.margin - totalsWidth;

    this.doc.setFontSize(9);

    // Subtotal
    this.doc.setFont('helvetica', 'normal');
    this.doc.setTextColor(TEXT_GREY.r, TEXT_GREY.g, TEXT_GREY.b);
    this.doc.text('Subtotal', totalsX, this.currentY);
    this.doc.setTextColor(TEXT_DARK.r, TEXT_DARK.g, TEXT_DARK.b);
    this.doc.text(this.formatMoney(invoice.subtotal), this.pageWidth - this.margin, this.currentY, { align: 'right' });
    this.currentY += 5;

    // VAT (if applicable)
    if (invoice.tax_rate > 0) {
      this.doc.setTextColor(TEXT_GREY.r, TEXT_GREY.g, TEXT_GREY.b);
      this.doc.text(`VAT (${invoice.tax_rate}%)`, totalsX, this.currentY);
      this.doc.setTextColor(TEXT_DARK.r, TEXT_DARK.g, TEXT_DARK.b);
      this.doc.text(this.formatMoney(invoice.tax_amount), this.pageWidth - this.margin, this.currentY, { align: 'right' });
      this.currentY += 5;
    }

    // Total
    this.doc.setDrawColor(DEEP_TEAL.r, DEEP_TEAL.g, DEEP_TEAL.b);
    this.doc.setLineWidth(0.3);
    this.doc.line(totalsX, this.currentY, this.pageWidth - this.margin, this.currentY);
    this.currentY += 4;

    this.doc.setFillColor(DEEP_TEAL.r, DEEP_TEAL.g, DEEP_TEAL.b);
    this.doc.roundedRect(totalsX - 2, this.currentY - 2, totalsWidth + 2, 10, 1, 1, 'F');

    this.doc.setFont('helvetica', 'bold');
    this.doc.setFontSize(10);
    this.doc.setTextColor(255, 255, 255);
    this.doc.text('TOTAL', totalsX, this.currentY + 5);
    this.doc.text(this.formatMoney(invoice.total), this.pageWidth - this.margin, this.currentY + 5, { align: 'right' });

    this.currentY += 15;
  }

  private renderBankingDetails(profile: BusinessProfile): void {
    if (!profile.bank_name || !profile.account_number) return;

    const boxWidth = this.pageWidth - (this.margin * 2);

    // Section header
    this.doc.setFillColor(DEEP_TEAL.r, DEEP_TEAL.g, DEEP_TEAL.b);
    this.doc.roundedRect(this.margin, this.currentY, boxWidth, 7, 1, 1, 'F');

    this.doc.setFontSize(9);
    this.doc.setFont('helvetica', 'bold');
    this.doc.setTextColor(255, 255, 255);
    this.doc.text('BANKING DETAILS', this.margin + 4, this.currentY + 5);

    this.currentY += 10;

    // Banking info in columns
    const colWidth = boxWidth / 4;
    this.doc.setFontSize(8);
    this.doc.setTextColor(TEXT_GREY.r, TEXT_GREY.g, TEXT_GREY.b);
    this.doc.setFont('helvetica', 'normal');

    const bankDetails = [
      { label: 'Bank', value: profile.bank_name },
      { label: 'Account Holder', value: profile.account_holder },
      { label: 'Account Number', value: profile.account_number },
      { label: 'Branch Code', value: profile.bank_branch_code },
    ];

    let colX = this.margin;
    bankDetails.forEach(({ label, value }) => {
      if (value) {
        this.doc.setTextColor(TEXT_GREY.r, TEXT_GREY.g, TEXT_GREY.b);
        this.doc.text(label, colX, this.currentY);
        this.doc.setTextColor(TEXT_DARK.r, TEXT_DARK.g, TEXT_DARK.b);
        this.doc.setFont('helvetica', 'bold');
        this.doc.text(value, colX, this.currentY + 4);
        this.doc.setFont('helvetica', 'normal');
      }
      colX += colWidth;
    });

    this.currentY += 12;
  }

  private renderNotes(notes: string): void {
    this.doc.setFontSize(8);
    this.doc.setFont('helvetica', 'bold');
    this.doc.setTextColor(TEXT_GREY.r, TEXT_GREY.g, TEXT_GREY.b);
    this.doc.text('Notes:', this.margin, this.currentY);

    this.doc.setFont('helvetica', 'normal');
    this.doc.setTextColor(TEXT_DARK.r, TEXT_DARK.g, TEXT_DARK.b);
    const noteLines = this.wrapText(notes, this.pageWidth - (this.margin * 2));
    noteLines.slice(0, 4).forEach(line => {
      this.currentY += 4;
      this.doc.text(line, this.margin, this.currentY);
    });

    this.currentY += 6;
  }

  private renderFooter(profile: BusinessProfile): void {
    const footerY = this.pageHeight - 15;

    // Footer line
    this.doc.setDrawColor(200, 200, 200);
    this.doc.setLineWidth(0.3);
    this.doc.line(this.margin, footerY - 3, this.pageWidth - this.margin, footerY - 3);

    // Payment terms
    this.doc.setFontSize(7);
    this.doc.setTextColor(TEXT_GREY.r, TEXT_GREY.g, TEXT_GREY.b);

    const footerText = profile.payment_terms || 'Payment due within 30 days of invoice date.';
    this.doc.text(footerText, this.margin, footerY);

    // Generated timestamp
    const timestamp = `Generated: ${new Date().toLocaleDateString('en-ZA')} | PennyPilot`;
    this.doc.text(timestamp, this.pageWidth - this.margin, footerY, { align: 'right' });
  }

  // === Utility Methods ===

  private formatMoney(amount: number): string {
    return 'R ' + amount.toLocaleString('en-ZA', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  }

  private formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('en-ZA', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  }

  private wrapText(text: string, maxWidth: number): string[] {
    const words = text.split(/\s+/);
    const lines: string[] = [];
    let currentLine = '';

    // Approximate character width (for 9pt font)
    const charWidth = 2;
    const maxChars = Math.floor(maxWidth / charWidth);

    words.forEach(word => {
      const testLine = currentLine ? `${currentLine} ${word}` : word;
      if (testLine.length > maxChars) {
        if (currentLine) lines.push(currentLine);
        currentLine = word;
      } else {
        currentLine = testLine;
      }
    });

    if (currentLine) lines.push(currentLine);
    return lines;
  }
}

// Singleton export
export const invoicePdfService = new InvoicePdfService();
