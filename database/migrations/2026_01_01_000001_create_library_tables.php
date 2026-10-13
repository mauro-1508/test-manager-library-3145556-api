<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE TABLE library.category (
            id uuid NOT NULL,
            name varchar(120) NOT NULL,
            CONSTRAINT category_pkey PRIMARY KEY (id),
            CONSTRAINT category_name_unique UNIQUE (name)
        )');

        DB::statement('CREATE TABLE library.book (
            id uuid NOT NULL,
            title varchar(250) NOT NULL,
            author varchar(200) NOT NULL,
            isbn char(13) NOT NULL,
            category_id uuid NOT NULL,
            total_copies integer NOT NULL,
            available_copies integer NOT NULL,
            cover_key varchar(512) NULL,
            deleted_at timestamptz NULL,
            CONSTRAINT book_pkey PRIMARY KEY (id),
            CONSTRAINT book_category_id_foreign FOREIGN KEY (category_id)
                REFERENCES library.category (id) ON DELETE RESTRICT ON UPDATE NO ACTION,
            CONSTRAINT ck_book_available_range CHECK (available_copies >= 0 AND available_copies <= total_copies)
        )');

        DB::statement('CREATE TABLE library.member (
            id uuid NOT NULL,
            full_name varchar(200) NOT NULL,
            document_number varchar(30) NOT NULL,
            CONSTRAINT member_pkey PRIMARY KEY (id),
            CONSTRAINT member_document_number_unique UNIQUE (document_number)
        )');

        DB::statement('CREATE TABLE library."user" (
            id uuid NOT NULL,
            username varchar(120) NOT NULL,
            password_hash varchar(512) NOT NULL,
            role varchar(40) NOT NULL,
            CONSTRAINT user_pkey PRIMARY KEY (id),
            CONSTRAINT user_username_unique UNIQUE (username)
        )');

        DB::statement('CREATE TABLE library.loan (
            id uuid NOT NULL,
            member_id uuid NOT NULL,
            loaned_by_user_id uuid NOT NULL,
            loaned_at timestamptz NOT NULL,
            CONSTRAINT loan_pkey PRIMARY KEY (id),
            CONSTRAINT loan_member_id_foreign FOREIGN KEY (member_id)
                REFERENCES library.member (id) ON DELETE RESTRICT ON UPDATE NO ACTION,
            CONSTRAINT loan_loaned_by_user_id_foreign FOREIGN KEY (loaned_by_user_id)
                REFERENCES library."user" (id) ON DELETE RESTRICT ON UPDATE NO ACTION
        )');

        DB::statement('CREATE TABLE library.loan_item (
            id uuid NOT NULL,
            loan_id uuid NOT NULL,
            book_id uuid NOT NULL,
            book_title varchar(250) NOT NULL,
            isbn char(13) NOT NULL,
            category_name varchar(120) NOT NULL,
            due_at timestamptz NOT NULL,
            returned_at timestamptz NULL,
            CONSTRAINT loan_item_pkey PRIMARY KEY (id),
            CONSTRAINT loan_item_loan_id_foreign FOREIGN KEY (loan_id)
                REFERENCES library.loan (id) ON DELETE CASCADE ON UPDATE NO ACTION,
            CONSTRAINT loan_item_book_id_foreign FOREIGN KEY (book_id)
                REFERENCES library.book (id) ON DELETE RESTRICT ON UPDATE NO ACTION,
            CONSTRAINT loan_item_loan_id_book_id_unique UNIQUE (loan_id, book_id)
        )');

        DB::statement('CREATE UNIQUE INDEX book_isbn_active_unique ON library.book (isbn) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX book_category_id_title_active_index ON library.book (category_id, title) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX loan_loaned_at_index ON library.loan (loaned_at)');
        DB::statement('CREATE INDEX loan_item_book_id_index ON library.loan_item (book_id)');
        DB::statement('CREATE INDEX loan_item_due_at_open_index ON library.loan_item (due_at) WHERE returned_at IS NULL');

        DB::table('library.category')->insert([
            ['id' => '11111111-1111-4111-8111-111111111111', 'name' => 'Novela'],
            ['id' => '22222222-2222-4222-8222-222222222222', 'name' => 'Ciencia'],
            ['id' => '33333333-3333-4333-8333-333333333333', 'name' => 'Tecnología'],
            ['id' => '44444444-4444-4444-8444-444444444444', 'name' => 'Historia'],
            ['id' => '55555555-5555-4555-8555-555555555555', 'name' => 'Infantil'],
        ]);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS library.loan_item');
        DB::statement('DROP TABLE IF EXISTS library.loan');
        DB::statement('DROP TABLE IF EXISTS library."user"');
        DB::statement('DROP TABLE IF EXISTS library.member');
        DB::statement('DROP TABLE IF EXISTS library.book');
        DB::statement('DROP TABLE IF EXISTS library.category');
    }
};
